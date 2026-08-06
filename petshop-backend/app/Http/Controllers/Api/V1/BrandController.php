<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class BrandController extends Controller
{
    /**
     * Public / Admin List Brands
     */
    public function index(): JsonResponse
    {
        $brands = Brand::withCount('products')->latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $brands,
        ]);
    }

    /**
     * Store a new Brand (Supports Image Upload or Image URL)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:brands,name'],
            'logo'      => ['nullable', 'string'], // URL option
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'], // Upload option
            'is_active' => ['boolean'],
        ]);

        $logoPath = $validated['logo'] ?? null;

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('brands', 'public');
            $logoPath = asset('storage/' . $path);
        }

        $brand = Brand::create([
            'name'      => $validated['name'],
            'slug'      => Str::slug($validated['name']),
            'logo'      => $logoPath,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Marque ajoutée avec succès!',
            'data'    => $brand,
        ], 201);
    }

    /**
     * Update specified Brand
     */
    public function update(Request $request, Brand $brand): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255', 'unique:brands,name,' . $brand->id],
            'logo'      => ['nullable', 'string'],
            'logo_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,svg', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $logoPath = $validated['logo'] ?? $brand->logo;

        if ($request->hasFile('logo_file')) {
            $path = $request->file('logo_file')->store('brands', 'public');
            $logoPath = asset('storage/' . $path);
        }

        $brand->update([
            'name'      => $validated['name'],
            'slug'      => Str::slug($validated['name']),
            'logo'      => $logoPath,
            'is_active' => $request->boolean('is_active', $brand->is_active),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Marque mise à jour avec succès!',
            'data'    => $brand,
        ]);
    }

    /**
     * Delete Brand
     */
    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Marque supprimée avec succès.',
        ]);
    }
}
