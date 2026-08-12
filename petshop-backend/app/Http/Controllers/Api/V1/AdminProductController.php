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
        $query = Product::with(['category', 'brand', 'stores']);

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
            'category_id'        => ['required', 'exists:categories,id'],
            'brand_id'           => ['nullable', 'exists:brands,id'],
            'barcode'            => ['required', 'string', 'max:255', 'unique:products,barcode'],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'price_buy'          => ['required', 'numeric', 'min:0'],
            'price_sell'         => ['required', 'numeric', 'min:0'],
            'price_per_kg'       => ['nullable', 'numeric', 'min:0'],
            'unit_type'          => ['nullable', 'string', 'in:PIECE,WEIGHT'],
            'stock_quantity'     => ['nullable', 'integer', 'min:0'],
            'quantity'           => ['nullable', 'integer', 'min:0'],
            'min_stock_alert'    => ['nullable', 'integer', 'min:0'],
            'alert_stock_level'  => ['nullable', 'integer', 'min:0'],
            'image'              => ['nullable', 'string'],
            'image_file'         => ['nullable', 'image', 'max:3072'], // Max 3MB
            'is_active'          => ['nullable'],
        ]);

        $imagePath = $validated['image'] ?? null;

        // Handle File Upload if an image file is selected
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('products', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : true;

        $product = Product::create([
            'barcode'           => $validated['barcode'],
            'title'             => $validated['title'],
            'description'       => $validated['description'] ?? null,
            'category_id'       => $validated['category_id'],
            'brand_id'          => $validated['brand_id'] ?? null,
            'stock_quantity'    => $validated['stock_quantity'] ?? $validated['quantity'] ?? 0,
            'price_buy'         => $validated['price_buy'],
            'price_sell'        => $validated['price_sell'],
            'price_per_kg'      => $validated['price_per_kg'] ?? (($validated['unit_type'] ?? 'PIECE') === 'WEIGHT' ? $validated['price_sell'] : null),
            'unit_type'         => $validated['unit_type'] ?? 'PIECE',
            'min_stock_alert'   => $validated['min_stock_alert'] ?? $validated['alert_stock_level'] ?? 5,
            'image'             => $imagePath,
            'is_active'         => $isActive,
        ]);

        $product->load(['category', 'brand']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit ajouté avec succès!',
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
            'category_id'        => ['nullable', 'exists:categories,id'],
            'brand_id'           => ['nullable', 'exists:brands,id'],
            'barcode'            => ['required', 'string', 'max:255', 'unique:products,barcode,' . $product->id],
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'price_buy'          => ['required', 'numeric', 'min:0'],
            'price_sell'         => ['required', 'numeric', 'min:0'],
            'price_per_kg'       => ['nullable', 'numeric', 'min:0'],
            'unit_type'          => ['nullable', 'string', 'in:PIECE,WEIGHT'],
            'stock_quantity'     => ['nullable', 'integer', 'min:0'],
            'quantity'           => ['nullable', 'integer', 'min:0'],
            'min_stock_alert'    => ['nullable', 'integer', 'min:0'],
            'alert_stock_level'  => ['nullable', 'integer', 'min:0'],
            'image'              => ['nullable', 'string'],
            'image_file'         => ['nullable', 'image', 'max:3072'],
            'is_active'          => ['nullable'],
        ]);

        $imagePath = $validated['image'] ?? $product->image;

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('products', 'public');
            $imagePath = asset('storage/' . $path);
        }

        $isActive = $request->has('is_active') ? $request->boolean('is_active') : $product->is_active;

        $product->update([
            'barcode'           => $validated['barcode'],
            'title'             => $validated['title'],
            'description'       => $validated['description'] ?? $product->description,
            'category_id'       => $validated['category_id'] ?? $product->category_id,
            'brand_id'          => $validated['brand_id'] ?? $product->brand_id,
            'stock_quantity'    => $validated['stock_quantity'] ?? $validated['quantity'] ?? $product->stock_quantity,
            'price_buy'         => $validated['price_buy'],
            'price_sell'        => $validated['price_sell'],
            'price_per_kg'      => $validated['price_per_kg'] ?? ((($validated['unit_type'] ?? $product->unit_type) === 'WEIGHT') ? $validated['price_sell'] : null),
            'unit_type'         => $validated['unit_type'] ?? $product->unit_type ?? 'PIECE',
            'min_stock_alert'   => $validated['min_stock_alert'] ?? $validated['alert_stock_level'] ?? $product->min_stock_alert,
            'image'             => $imagePath,
            'is_active'         => $isActive,
        ]);

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
