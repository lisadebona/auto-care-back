<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->when($request->input('search'), function ($query, string $search): void {
                $query->where('name', 'like', "%{$search}%");
            })
            ->withCount('products')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class, 'name')],
        ]);

        $category = Category::query()->create($validated);

        return response()->json($category->loadCount('products'), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Category::class, 'name')->ignore($category->id)],
        ]);

        $category->update($validated);

        return response()->json($category->loadCount('products'));
    }

    public function destroy(Category $category): JsonResponse
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category still has products. Move them to another category first.',
            ]);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted successfully.']);
    }
}
