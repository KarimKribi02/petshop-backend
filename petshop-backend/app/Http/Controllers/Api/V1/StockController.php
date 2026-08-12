<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class StockController extends Controller
{
    /**
     * Scan-In Stock Entry (Magasinier / Multi-Store)
     * Increments product global stock and store-specific stock, and creates stock movement.
     */
    public function scanIn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'barcode'     => ['nullable', 'string'],
            'product_id'  => ['nullable', 'exists:products,id'],
            'quantity'    => ['required', 'numeric', 'min:0.01'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'store_id'    => ['nullable', 'exists:stores,id'],
            'bl_number'   => ['nullable', 'string', 'max:255'],
            'notes'       => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $storeId = $validated['store_id'] ?? ($user ? $user->store_id : null);

        // Find product by barcode or ID
        $product = null;
        if (!empty($validated['barcode'])) {
            $product = Product::where('barcode', trim($validated['barcode']))->first();
        } elseif (!empty($validated['product_id'])) {
            $product = Product::find($validated['product_id']);
        }

        if (! $product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé avec les informations fournies.',
            ], 404);
        }

        $stockMovement = DB::transaction(function () use ($product, $validated, $user, $storeId) {
            // 1. Update Product Global Stock
            $product->increment('stock_quantity', $validated['quantity']);

            // 2. Update Store-Specific Stock in store_product_stock table
            if ($storeId) {
                $storeStock = DB::table('store_product_stock')
                    ->where('product_id', $product->id)
                    ->where('store_id', $storeId)
                    ->first();

                if ($storeStock) {
                    DB::table('store_product_stock')
                        ->where('product_id', $product->id)
                        ->where('store_id', $storeId)
                        ->increment('quantity', $validated['quantity'], ['updated_at' => now()]);
                } else {
                    DB::table('store_product_stock')->insert([
                        'product_id' => $product->id,
                        'store_id'   => $storeId,
                        'quantity'   => $validated['quantity'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // 3. Create Stock Movement Record
            $movementData = [
                'product_id'  => $product->id,
                'user_id'     => $user ? $user->id : null,
                'store_id'    => $storeId,
                'type'        => 'IN',
                'quantity'    => $validated['quantity'],
                'source'      => 'MAGASINIER_SCAN',
                'notes'       => $validated['notes'] ?? null,
                'supplier_id' => $validated['supplier_id'] ?? null,
                'bl_number'   => $validated['bl_number'] ?? null,
            ];

            return StockMovement::create($movementData);
        });

        // Reload fresh product stock
        $product->refresh();

        $storeName = $user && $user->store ? $user->store->name : 'Magasin Principal';

        return response()->json([
            'status'  => 'success',
            'message' => 'Entrée de stock enregistrée avec succès pour ' . $storeName,
            'data'    => [
                'product'        => $product,
                'stock_movement' => $stockMovement->load(['product', 'supplier', 'store', 'user']),
            ],
        ], 201);
    }

    /**
     * Alias for storeStockEntry()
     */
    public function storeStockEntry(Request $request): JsonResponse
    {
        return $this->scanIn($request);
    }

    /**
     * Find product by barcode (Shared for Barcode Scanner)
     */
    public function findByBarcode(string $barcode, Request $request): JsonResponse
    {
        $cleanBarcode = trim($barcode);

        $product = Product::with(['category', 'brand', 'stores'])->where('barcode', $cleanBarcode)->first();

        if (! $product) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'Produit non trouvé avec ce code-barres (' . $cleanBarcode . ').',
                'data'    => null,
            ], 200);
        }

        // Include store-specific quantity if user is tied to a store
        $user = $request->user();
        if ($user && $user->store_id) {
            $storeStock = DB::table('store_product_stock')
                ->where('product_id', $product->id)
                ->where('store_id', $user->store_id)
                ->value('quantity');

            $product->store_stock_quantity = $storeStock !== null ? (float) $storeStock : 0.0;
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

        $query = StockMovement::with(['product', 'user', 'supplier', 'store']);

        $user = $request->user();
        if ($user && $user->hasRole('MAGASINIER')) {
            if ($user->store_id) {
                $query->where('store_id', $user->store_id);
            } else {
                $query->where('user_id', $user->id);
            }
        }

        $movements = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $movements,
        ], 200);
    }

    /**
     * Safe & Robust Purchases History Endpoint
     */
    public function supplierHistory(Request $request): JsonResponse
    {
        try {
            $query = StockMovement::query()
                ->where('type', 'IN');

            $withRelations = [
                'product' => function ($q) {
                    $q->select('id', 'title', 'barcode', 'price_buy');
                },
                'supplier' => function ($q) {
                    $q->select('id', 'company_name');
                },
                'user' => function ($q) {
                    $q->select('id', 'name');
                },
                'store' => function ($q) {
                    $q->select('id', 'name', 'code');
                }
            ];

            $query->with($withRelations);

            $user = $request->user();

            // Restrict Magasinier to their own stock entries / store
            if ($user && $user->hasRole('MAGASINIER')) {
                if ($user->store_id) {
                    $query->where('store_id', $user->store_id);
                } else {
                    $query->where('user_id', $user->id);
                }
            } elseif ($request->filled('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->filled('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            if ($request->filled('supplier_id')) {
                $query->where('supplier_id', $request->supplier_id);
            }

            if ($request->filled('bl_number')) {
                $query->where('bl_number', 'LIKE', '%' . $request->bl_number . '%');
            }

            $allMovements = (clone $query)->get();
            $totalQuantity = (int) $allMovements->sum('quantity');
            
            $totalAmount = (float) $allMovements->sum(function ($item) {
                $unitPrice = $item->unit_cost ?? $item->product?->price_buy ?? 0;
                return (float) ($item->quantity * (float) $unitPrice);
            });

            $movements = $query->latest()->paginate(50);

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'movements'      => $movements,
                    'total_quantity' => $totalQuantity,
                    'total_amount'   => $totalAmount,
                ],
            ], 200);

        } catch (\Throwable $e) {
            Log::error("Erreur Supplier History: " . $e->getMessage() . " à la ligne " . $e->getLine() . " dans " . $e->getFile());

            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur Serveur: ' . $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }
}
