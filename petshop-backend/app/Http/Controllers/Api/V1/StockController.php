<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class StockController extends Controller
{
    /**
     * Scan-In Stock Entry (Magasinier)
     * Increments product stock quantity and creates a stock movement record of type IN and source MAGASINIER_SCAN.
     */
    public function scanIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'     => ['required', 'string'],
            'quantity'    => ['required', 'integer', 'min:1'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'bl_number'   => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string'],
        ]);

        $product = Product::where('barcode', $validated['barcode'])->first();

        if (! $product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé avec ce code-barres.',
            ], 404);
        }

        $stockMovement = DB::transaction(function () use ($product, $validated, $request) {
            // 1. Update Product Stock
            $product->increment('stock_quantity', $validated['quantity']);

            // 2. Create Stock Movement Record
            return StockMovement::create([
                'product_id'  => $product->id,
                'user_id'     => $request->user()->id,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bl_number'   => $validated['bl_number'] ?? null,
                'type'        => 'IN',
                'quantity'    => $validated['quantity'],
                'source'      => 'MAGASINIER_SCAN',
                'notes'       => $validated['notes'] ?? null,
            ]);
        });

        // Reload fresh product stock
        $product->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Entrée de stock enregistrée avec succès',
            'data'    => [
                'product' => [
                    'id'             => $product->id,
                    'barcode'        => $product->barcode,
                    'title'          => $product->title,
                    'stock_quantity' => $product->stock_quantity,
                    'min_stock_alert'=> $product->min_stock_alert,
                ],
                'stock_movement' => $stockMovement->load('supplier'),
            ]
        ], 200);
    }

    /**
     * Find product by barcode (Magasinier / Admin / POS Caisse)
     */
    public function findByBarcode(string $barcode): JsonResponse
    {
        // Clean raw barcode input from scanner layout shifts
        $cleanBarcode = trim($barcode);

        $product = Product::with(['category', 'brand'])->where('barcode', $cleanBarcode)->first();

        if (! $product) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'Produit non trouvé avec ce code-barres (' . $cleanBarcode . ').',
                'data'    => null,
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $product,
        ], 200);
    }

    /**
     * Get stock movement history (Magasinier / Admin)
     */
    public function history(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 15);

        $movements = StockMovement::with(['product', 'user', 'supplier'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $movements,
        ], 200);
    }

    /**
     * Get Detailed Purchases History by Supplier
     */
    public function supplierHistory(Request $request): JsonResponse
    {
        $query = StockMovement::with(['product:id,title,barcode,price_buy', 'supplier:id,company_name', 'user:id,name'])
            ->where('type', 'IN');

        // Filter by specific supplier if provided
        if ($request->has('supplier_id') && !empty($request->supplier_id)) {
            $query->where('supplier_id', $request->supplier_id);
        }

        // Filter by BL number if provided
        if ($request->has('bl_number') && !empty($request->bl_number)) {
            $query->where('bl_number', 'LIKE', "%{$request->bl_number}%");
        }

        // Calculate aggregated totals before paginating
        $totalQuantity = (clone $query)->sum('quantity');

        $movements = $query->latest()->paginate(50);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'movements'      => $movements,
                'total_quantity' => $totalQuantity,
            ],
        ]);
    }
}
