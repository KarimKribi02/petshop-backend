<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminProductController extends Controller
{
    /**
     * Display a listing of all products (including inactive).
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand']);

        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('barcode', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('brand_id') && !empty($request->brand_id)) {
            $query->where('brand_id', $request->brand_id);
        }

        $perPage = $request->get('per_page', 100);
        $products = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $products,
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id'     => ['nullable', 'exists:categories,id'],
            'brand_id'        => ['nullable', 'exists:brands,id'],
            'barcode'         => ['required', 'string', 'max:255', 'unique:products,barcode'],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'price_buy'       => ['required', 'numeric', 'min:0'],
            'price_sell'      => ['required', 'numeric', 'min:0'],
            'stock_quantity'  => ['required', 'integer', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $validated['min_stock_alert'] = $validated['min_stock_alert'] ?? 5;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $product = Product::create($validated);
        $product->load(['category', 'brand']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit créé avec succès.',
            'data'    => $product,
        ], 201);
    }

    /**
     * Display the specified product.
     */
    public function show(string $id): JsonResponse
    {
        $product = Product::with(['category', 'brand'])->find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $product,
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé.',
            ], 404);
        }

        $validated = $request->validate([
            'category_id'     => ['nullable', 'exists:categories,id'],
            'brand_id'        => ['nullable', 'exists:brands,id'],
            'barcode'         => ['required', 'string', 'max:255', 'unique:products,barcode,' . $product->id],
            'title'           => ['required', 'string', 'max:255'],
            'description'     => ['nullable', 'string'],
            'price_buy'       => ['required', 'numeric', 'min:0'],
            'price_sell'      => ['required', 'numeric', 'min:0'],
            'stock_quantity'  => ['required', 'integer', 'min:0'],
            'min_stock_alert' => ['nullable', 'integer', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
        ]);

        $product->update($validated);
        $product->load(['category', 'brand']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit mis à jour avec succès.',
            'data'    => $product,
        ]);
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé.',
            ], 404);
        }

        $product->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit supprimé avec succès.',
        ]);
    }

    /**
     * Generate Next Unique Sequential Barcode
     * Format: 200 + 9-digit auto-increment ID
     */
    public function generateUniqueBarcode(): JsonResponse
    {
        // Fetch last product ID to generate a strictly unique sequential number
        $lastProduct = Product::latest('id')->first();
        $nextId = $lastProduct ? $lastProduct->id + 1 : 1;
        
        // Format: 200000000001, 200000000002...
        $generatedBarcode = '200' . str_pad((string) $nextId, 9, '0', STR_PAD_LEFT);

        // Double check uniqueness in Database
        while (Product::where('barcode', $generatedBarcode)->exists()) {
            $nextId++;
            $generatedBarcode = '200' . str_pad((string) $nextId, 9, '0', STR_PAD_LEFT);
        }

        return response()->json([
            'status' => 'success',
            'barcode' => $generatedBarcode
        ]);
    }
}
