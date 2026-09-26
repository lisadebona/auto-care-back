<?php

namespace App\Http\Controllers;

use App\Models\Brand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BrandController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $brands = Brand::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($brands);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Brand::class, 'name')],
        ]);

        $brand = Brand::query()->create($validated);

        return response()->json($brand->loadCount('products'), 201);
    }

    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Brand::class, 'name')->ignore($brand->id)],
        ]);

        $brand->update($validated);

        return response()->json($brand->loadCount('products'));
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json(['message' => 'Brand deleted successfully.']);
    }
}
