<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $products = Product::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('upc_code', 'like', "%{$search}%")
                        ->orWhere('part_number', 'like', "%{$search}%");
                });
            })
            ->with(['brand:id,name', 'category:id,name', 'images', 'mainImage'])
            ->latest()
            ->get()
            ->map(fn (Product $product): array => $this->payload($product));

        return response()->json([
            'products' => $products,
            'brands' => Brand::query()->orderBy('name')->get(['id', 'name']),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate($this->rules());
        $this->validateGallery($validated);

        $storedPaths = [];
        $mainNewImageIndex = isset($validated['main_new_image_index'])
            ? (int) $validated['main_new_image_index']
            : 0;

        try {
            $product = DB::transaction(function () use ($validated, $mainNewImageIndex, &$storedPaths): Product {
                $product = Product::query()->create($this->attributes($validated));

                foreach ($validated['images'] ?? [] as $index => $image) {
                    $path = $image->store('products', 'public');
                    $storedPaths[] = $path;

                    $product->images()->create([
                        'path' => $path,
                        'is_main' => $index === $mainNewImageIndex,
                        'sort_order' => $index,
                    ]);
                }

                return $product;
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        return response()->json($this->payload($product->load(['brand:id,name', 'category:id,name', 'images', 'mainImage'])), 201);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            ...$this->rules(),
            'retained_image_ids' => ['nullable', 'array'],
            'retained_image_ids.*' => [
                'integer',
                Rule::exists(ProductImage::class, 'id')->where('product_id', $product->id),
            ],
            'main_existing_image_id' => [
                'nullable',
                'integer',
                Rule::exists(ProductImage::class, 'id')->where('product_id', $product->id),
            ],
            'manage_images' => ['nullable', 'boolean'],
        ]);

        $retainedImageIds = array_map(
            intval(...),
            $request->boolean('manage_images')
                ? ($validated['retained_image_ids'] ?? [])
                : ($validated['retained_image_ids'] ?? $product->images()->pluck('id')->all()),
        );
        $mainExistingImageId = isset($validated['main_existing_image_id'])
            ? (int) $validated['main_existing_image_id']
            : null;
        $mainNewImageIndex = isset($validated['main_new_image_index'])
            ? (int) $validated['main_new_image_index']
            : null;
        $this->validateGallery([
            ...$validated,
            'retained_image_ids' => $retainedImageIds,
        ]);

        if ($mainExistingImageId !== null && ! in_array($mainExistingImageId, $retainedImageIds, true)) {
            throw ValidationException::withMessages([
                'main_existing_image_id' => 'The main photo must be retained in the gallery.',
            ]);
        }

        $existingImages = $product->images()->get();
        $removedImages = $existingImages->whereNotIn('id', $retainedImageIds);
        $storedPaths = [];

        try {
            DB::transaction(function () use (
                $product,
                $validated,
                $retainedImageIds,
                $mainExistingImageId,
                $mainNewImageIndex,
                &$storedPaths,
            ): void {
                $product->update($this->attributes($validated));
                $product->images()->whereNotIn('id', $retainedImageIds)->delete();
                $product->images()->update(['is_main' => false]);

                foreach ($retainedImageIds as $sortOrder => $imageId) {
                    $product->images()->whereKey($imageId)->update([
                        'is_main' => $imageId === $mainExistingImageId,
                        'sort_order' => $sortOrder,
                    ]);
                }

                foreach ($validated['images'] ?? [] as $index => $image) {
                    $path = $image->store('products', 'public');
                    $storedPaths[] = $path;
                    $product->images()->create([
                        'path' => $path,
                        'is_main' => $index === $mainNewImageIndex,
                        'sort_order' => count($retainedImageIds) + $index,
                    ]);
                }

                if (! $product->images()->where('is_main', true)->exists()) {
                    $product->images()->orderBy('sort_order')->orderBy('id')->first()?->update(['is_main' => true]);
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($storedPaths);

            throw $exception;
        }

        Storage::disk('public')->delete($removedImages->pluck('path')->all());

        return response()->json($this->payload($product->refresh()->load(['brand:id,name', 'category:id,name', 'images', 'mainImage'])));
    }

    public function destroy(Product $product): JsonResponse
    {
        $imagePaths = $product->images()->pluck('path')->all();

        if ($product->image_path) {
            $imagePaths[] = $product->image_path;
        }

        $product->delete();
        Storage::disk('public')->delete(array_unique($imagePaths));

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'margin' => ['required', 'numeric', 'min:0', 'max:1000'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'brand_id' => ['nullable', 'integer', Rule::exists(Brand::class, 'id')],
            'category_id' => ['required', 'integer', Rule::exists(Category::class, 'id')],
            'upc_code' => ['nullable', 'string', 'max:255'],
            'part_number' => ['nullable', 'string', 'max:255'],
            'is_taxable' => ['required', 'boolean'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*' => ['image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'main_new_image_index' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(array $validated): array
    {
        return [
            ...collect($validated)->except([
                'images',
                'retained_image_ids',
                'main_existing_image_id',
                'main_new_image_index',
                'manage_images',
            ])->all(),
            'stock_quantity' => $validated['stock_quantity'] ?? 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function validateGallery(array $validated): void
    {
        $newImages = $validated['images'] ?? [];
        $retainedImageIds = $validated['retained_image_ids'] ?? [];

        if (count($newImages) + count($retainedImageIds) > 10) {
            throw ValidationException::withMessages([
                'images' => 'A product gallery may contain no more than 10 images.',
            ]);
        }

        $mainNewImageIndex = $validated['main_new_image_index'] ?? null;

        if ($mainNewImageIndex !== null && ! array_key_exists($mainNewImageIndex, $newImages)) {
            throw ValidationException::withMessages([
                'main_new_image_index' => 'The selected main photo is invalid.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Product $product): array
    {
        $product->loadMissing(['brand:id,name', 'category:id,name', 'images', 'mainImage']);

        return [
            'id' => $product->id,
            'name' => $product->name,
            'description' => $product->description,
            'unit_price' => $product->unit_price,
            'margin' => $product->margin,
            'retail_price' => $product->retail_price,
            'stock_quantity' => $product->stock_quantity,
            'brand_id' => $product->brand_id,
            'brand_name' => $product->brand?->name,
            'category_id' => $product->category_id,
            'category_name' => $product->category?->name,
            'upc_code' => $product->upc_code,
            'part_number' => $product->part_number,
            'is_taxable' => $product->is_taxable,
            'image_url' => $product->image_url,
            'images' => $product->images->map(fn (ProductImage $image): array => [
                'id' => $image->id,
                'url' => $image->url,
                'is_main' => $image->is_main,
            ])->values(),
        ];
    }
}
