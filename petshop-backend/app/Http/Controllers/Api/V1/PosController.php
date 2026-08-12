<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PosController extends Controller
{
    /**
     * POS Checkout Engine (Multi-Store Caisse Order)
     * Handles store-specific & global stock reduction, order creation atomically.
     */
    public function checkout(Request $request): JsonResponse
    {
        // 1. Validation du panier
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:CASH,CARD,TPE,OTHER'],
            'store_id'       => ['nullable', 'exists:stores,id'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.barcode' => ['required', 'string', 'exists:products,barcode'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.001'],
        ]);

        try {
            $user = $request->user();
            $storeId = $validated['store_id'] ?? ($user ? $user->store_id : null);

            // 2. MySQL Atomic Transaction Engine
            $orderData = DB::transaction(function () use ($validated, $user, $storeId) {

                $totalAmount = 0;
                $orderItemsToInsert = [];
                $stockMovementsToInsert = [];
                $updatedProducts = [];

                // Loop through all items in the cart
                foreach ($validated['items'] as $item) {

                    // Lock product row for atomic update
                    $product = Product::where('barcode', $item['barcode'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Check if stock is sufficient (Store specific if cashier assigned to store, else global)
                    if ($storeId) {
                        $storeStock = DB::table('store_product_stock')
                            ->where('product_id', $product->id)
                            ->where('store_id', $storeId)
                            ->value('quantity');

                        if ($storeStock !== null && (float) $storeStock < (float) $item['quantity']) {
                            throw new \Exception("Stock insuffisant dans votre magasin pour: {$product->title}. Disponible: {$storeStock}");
                        } elseif ($storeStock === null && $product->stock_quantity < $item['quantity']) {
                            throw new \Exception("Stock insuffisant pour: {$product->title}. Disponible: {$product->stock_quantity}");
                        }
                    } else {
                        if ($product->stock_quantity < $item['quantity']) {
                            throw new \Exception("Stock insuffisant pour le produit: {$product->title}. Stock disponible: {$product->stock_quantity}");
                        }
                    }

                    $itemPrice = (float) $product->price_sell;
                    $subtotal = $itemPrice * $item['quantity'];
                    $totalAmount += $subtotal;

                    // Deduct Global Product Stock
                    $product->decrement('stock_quantity', $item['quantity']);

                    // Deduct Store-Specific Stock in store_product_stock table
                    if ($storeId) {
                        $existingStoreStock = DB::table('store_product_stock')
                            ->where('product_id', $product->id)
                            ->where('store_id', $storeId)
                            ->first();

                        if ($existingStoreStock) {
                            DB::table('store_product_stock')
                                ->where('product_id', $product->id)
                                ->where('store_id', $storeId)
                                ->decrement('quantity', $item['quantity']);
                        } else {
                            DB::table('store_product_stock')->insert([
                                'product_id' => $product->id,
                                'store_id'   => $storeId,
                                'quantity'   => max(0, $product->stock_quantity),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Collect item for bulk DB insert
                    $orderItemsToInsert[] = [
                        'product_id' => $product->id,
                        'quantity'   => $item['quantity'],
                        'unit_price' => $itemPrice,
                        'title'      => $product->title,
                    ];

                    // Audit trail entry
                    $stockMovementsToInsert[] = [
                        'product_id' => $product->id,
                        'user_id'    => $user ? $user->id : null,
                        'store_id'   => $storeId,
                        'type'       => 'OUT',
                        'quantity'   => $item['quantity'],
                        'source'     => 'POS_SALE',
                        'notes'      => 'Vente en caisse (POS)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $updatedProducts[] = $product->fresh();
                }

                // Create POS Order Record with store_id
                $order = Order::create([
                    'user_id'        => $user ? $user->id : null,
                    'store_id'       => $storeId,
                    'order_type'     => 'POS',
                    'source'         => 'POS',
                    'total_amount'   => $totalAmount,
                    'status'         => 'COMPLETED',
                    'payment_method' => $validated['payment_method'],
                ]);

                // Attach Order ID to Items and insert bulk
                foreach ($orderItemsToInsert as $itemData) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $itemData['product_id'],
                        'quantity'   => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ]);
                }

                // Insert Stock Movements
                StockMovement::insert($stockMovementsToInsert);

                return [
                    'order'    => $order->load(['orderItems.product', 'store']),
                    'caissier' => $user ? $user->name : 'Caissier',
                    'store'    => $order->store,
                ];
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Vente effectuée avec succès!',
                'data'    => $orderData,
            ], 201);

        } catch (\Exception $e) {
            Log::error('POS Checkout Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Sales History & Analytics Report API
     */
    public function salesHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['orderItems.product:id,title,barcode', 'user:id,name', 'store:id,name,code']);

        // If logged-in user is CAISSIER, automatically restrict history to their own sales or store
        if ($user && $user->hasRole('CAISSIER')) {
            $query->where('user_id', $user->id)
                  ->where('status', 'COMPLETED');
        } else {
            // For ADMIN: allow all non-cancelled orders by default, or filter by status
            if ($request->has('status') && !empty($request->status)) {
                $query->where('status', $request->status);
            } else {
                $query->where('status', '!=', 'CANCELLED');
            }

            if ($request->has('user_id') && !empty($request->user_id)) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('store_id') && !empty($request->store_id)) {
                $query->where('store_id', $request->store_id);
            }

            if ($request->has('source') && !empty($request->source)) {
                $query->where(function ($q) use ($request) {
                    $q->where('source', $request->source)
                      ->orWhere('order_type', $request->source);
                });
            }
        }

        // Apply Date Presets (today, month, year)
        if ($request->has('preset')) {
            switch ($request->preset) {
                case 'today':
                    $query->whereDate('created_at', now()->today());
                    break;
                case 'month':
                    $query->whereMonth('created_at', now()->month)
                        ->whereYear('created_at', now()->year);
                    break;
                case 'year':
                    $query->whereYear('created_at', now()->year);
                    break;
            }
        } elseif ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('created_at', [$request->start_date . ' 00:00:00', $request->end_date . ' 23:59:59']);
        }

        // Calculate aggregated revenue totals
        $totalRevenue = (clone $query)->sum('total_amount');
        $totalOrdersCount = (clone $query)->count();

        $orders = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'orders'             => ['data' => $orders],
                'total_revenue'      => (float) $totalRevenue,
                'total_orders_count' => (int) $totalOrdersCount,
            ],
        ]);
    }

    /**
     * Get POS Catalog Products with Store-Specific Stock
     */
    public function getPosProducts(Request $request): JsonResponse
    {
        $user = $request->user();
        $storeId = $request->get('store_id') ?: ($user ? $user->store_id : null);

        // Fetch products with store-specific stock quantity
        $products = Product::with(['category', 'brand', 'stores'])
            ->where('is_active', true)
            ->get()
            ->map(function ($product) use ($storeId) {
                // Get store specific quantity from pivot table
                $storeStock = null;
                if ($storeId) {
                    $storeStock = DB::table('store_product_stock')
                        ->where('product_id', $product->id)
                        ->where('store_id', $storeId)
                        ->value('quantity');
                }

                $globalStock = $product->stock_quantity ?? $product->quantity ?? 0;
                // Fallback: If store stock record exists, use it; else fallback to global quantity
                $effectiveQuantity = $storeStock !== null ? (float)$storeStock : (float)$globalStock;

                return [
                    'id'             => $product->id,
                    'title'          => $product->title,
                    'barcode'        => $product->barcode,
                    'price_sell'     => (float) $product->price_sell,
                    'price_buy'      => (float) $product->price_buy,
                    'image'          => $product->image,
                    'unit_type'      => $product->unit_type ?? 'PIECE',
                    'category_id'    => $product->category_id,
                    'category'       => $product->category,
                    'brand'          => $product->brand,
                    'stock_quantity' => $effectiveQuantity,
                    'quantity'       => $effectiveQuantity, // Store specific stock
                    'quantity_stock' => $effectiveQuantity,
                    'stores'         => $product->stores,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $products,
        ]);
    }
}
