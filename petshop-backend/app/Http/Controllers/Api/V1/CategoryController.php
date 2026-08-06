<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    /**
     * Public / Admin List Categories with Children & Product Count
     */
    public function index(): JsonResponse
    {
        // Get all categories with parent info and products count
        $categories = Category::with('parent:id,name')
            ->withCount('products')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => $categories,
        ]);
    }

    /**
     * Store new Category or Subcategory
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'string'],
            'image_file'  => ['nullable', 'image', 'max:2048'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'is_active'   => ['boolean'],
        ]);

        $imagePath = $validated['image'] ?? null;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('categories', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $category = Category::create([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'image'       => $imagePath,
            'parent_id'   => $validated['parent_id'] ?? null,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catégorie ajoutée avec succès!',
            'data'    => $category->load('parent'),
        ], 201);
    }

    /**
     * Update Category / Subcategory
     */
    public function update(Request $request, Category $category): JsonResponse
    {
        $validated = $request->validate([
            'name'        => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image'       => ['nullable', 'string'],
            'image_file'  => ['nullable', 'image', 'max:2048'],
            'parent_id'   => ['nullable', 'exists:categories,id'],
            'is_active'   => ['boolean'],
        ]);

        $imagePath = $validated['image'] ?? $category->image;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('categories', 'public');
            $imagePath = asset('storage/' . $path);
        }

        // Prevent category from setting itself as parent
        if (isset($validated['parent_id']) && $validated['parent_id'] == $category->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Une catégorie ne peut pas être sa propre catégorie parente.',
            ], 422);
        }

        $category->update([
            'name'        => $validated['name'],
            'slug'        => Str::slug($validated['name']),
            'description' => $validated['description'] ?? $category->description,
            'image'       => $imagePath,
            'parent_id'   => $validated['parent_id'] ?? null,
            'is_active'   => $request->boolean('is_active', $category->is_active),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catégorie mise à jour!',
            'data'    => $category->load('parent'),
        ]);
    }

    /**
     * Delete Category
     */
    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catégorie supprimée avec succès.',
        ]);
    }
}
