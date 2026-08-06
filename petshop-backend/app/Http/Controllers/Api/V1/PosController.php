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
     * POS Checkout Engine (Caisse Multi-Item Order)
     * Handles stock reduction and order creation atomically f MySQL
     */
    public function checkout(Request $request): JsonResponse
    {
        // 1. Validation dyal Input (Panier dyal l-Caissier)
        $validated = $request->validate([
            'payment_method' => ['required', 'string', 'in:CASH,CARD,OTHER'],
            'items'          => ['required', 'array', 'min:1'],
            'items.*.barcode'=> ['required', 'string', 'exists:products,barcode'],
            'items.*.quantity'=> ['required', 'integer', 'min:1'],
        ]);

        try {
            // 2. MySQL Atomic Transaction Engine
            $orderData = DB::transaction(function () use ($validated, $request) {
                
                $totalAmount = 0;
                $orderItemsToInsert = [];
                $stockMovementsToInsert = [];
                $updatedProducts = [];

                // Loop through all items in the cart
                foreach ($validated['items'] as $item) {
                    
                    // Lock product row for atomic update (prevents double selling)
                    $product = Product::where('barcode', $item['barcode'])
                        ->lockForUpdate()
                        ->firstOrFail();

                    // Check if stock is sufficient
                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Stock insuffisant pour le produit: {$product->title}. Stock disponible: {$product->stock_quantity}");
                    }

                    $itemPrice = $product->price_sell;
                    $subtotal = $itemPrice * $item['quantity'];
                    $totalAmount += $subtotal;

                    // Deduct Stock
                    $product->decrement('stock_quantity', $item['quantity']);

                    // Collect item for bulk DB insert
                    $orderItemsToInsert[] = [
                        'product_id' => $product->id,
                        'quantity'   => $item['quantity'],
                        'unit_price' => $itemPrice,
                        'title'      => $product->title, // For response ticket
                    ];

                    // Audit trail entry
                    $stockMovementsToInsert[] = [
                        'product_id' => $product->id,
                        'user_id'    => $request->user()->id,
                        'type'       => 'OUT',
                        'quantity'   => $item['quantity'],
                        'source'     => 'POS_SALE',
                        'notes'      => 'Vente en caisse (POS)',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $updatedProducts[] = $product->fresh();
                }

                // Create POS Order Record
                $order = Order::create([
                    'user_id'        => $request->user()->id, // Caissier User ID
                    'order_type'     => 'POS',
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
                    'order'    => $order->load('orderItems.product'),
                    'caissier' => $request->user()->name,
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
        $query = Order::with(['orderItems.product:id,title,barcode', 'user:id,name'])
            ->where('status', 'COMPLETED');

        // Filter by Caissier / User
        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        // Filter by Date Range or Preset (Today, Month, Year)
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

        // Calculate aggregated revenue totals before paginating
        $totalRevenue = (clone $query)->sum('total_amount');
        $totalOrdersCount = (clone $query)->count();

        $orders = $query->latest()->paginate(100);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'orders'             => $orders,
                'total_revenue'      => $totalRevenue,
                'total_orders_count' => $totalOrdersCount,
            ],
        ]);
    }
}
