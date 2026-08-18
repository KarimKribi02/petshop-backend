<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Store;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OrderController extends Controller
{
    /**
     * Store Public Web Order (Storefront COD Checkout)
     * Handles delivery_type (LIVRAISON / PICKUP_STORE) and store stock deduction.
     * Calculates total dynamically from DB products table to prevent client-side price manipulation.
     */
    public function storeWebOrder(Request $request): JsonResponse
    {
        // Normalize alias input keys if passed by different frontend clients
        if (!$request->has('phone') && $request->has('customer_phone')) {
            $request->merge(['phone' => $request->input('customer_phone')]);
        }
        if (!$request->has('city') && $request->has('customer_city')) {
            $request->merge(['city' => $request->input('customer_city')]);
        }
        if (!$request->has('address') && $request->has('customer_address')) {
            $request->merge(['address' => $request->input('customer_address')]);
        }
        if (!$request->has('customer_name') && $request->has('fullName')) {
            $request->merge(['customer_name' => $request->input('fullName')]);
        }
        if (!$request->has('delivery_type') || empty($request->input('delivery_type'))) {
            $request->merge(['delivery_type' => 'LIVRAISON']);
        }
        if (!$request->has('store_id') || empty($request->input('store_id'))) {
            $defaultStore = Store::where('is_active', true)->first() ?? Store::first();
            if (!$defaultStore) {
                $defaultStore = Store::create([
                    'name'      => 'Magasin Principal',
                    'code'      => 'STORE_MAIN',
                    'is_active' => true,
                ]);
            }
            $request->merge(['store_id' => $defaultStore->id]);
        }

        // Auto-resolve product_id from barcode or id if needed
        $items = $request->input('items', []);
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (empty($item['product_id']) && !empty($item['id'])) {
                    $item['product_id'] = $item['id'];
                }
                if (empty($item['product_id']) && !empty($item['barcode'])) {
                    $foundProduct = Product::where('barcode', $item['barcode'])->first();
                    if ($foundProduct) {
                        $item['product_id'] = $foundProduct->id;
                    }
                }
            }
            $request->merge(['items' => $items]);
        }

        $validated = $request->validate([
            'customer_name'      => ['required', 'string', 'max:255'],
            'phone'              => ['required', 'string', 'max:50'],
            'city'               => ['required', 'string', 'max:100'],
            'address'            => ['nullable', 'string'],
            'store_id'           => ['required', 'exists:stores,id'],
            'delivery_type'      => ['required', 'in:LIVRAISON,PICKUP_STORE'],
            'items'              => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity'   => ['required', 'numeric', 'min:0.01'],
            'shipping_fee'       => ['nullable', 'numeric', 'min:0'],
            'notes'              => ['nullable', 'string', 'max:1000'],
            'payment_method'     => ['nullable', 'string', 'max:50'],
        ]);

        try {
            $order = DB::transaction(function () use ($validated, $request) {
                $itemsTotal = 0;
                $orderItemsData = [];

                // Calculate total from products database to prevent manipulation
                foreach ($validated['items'] as $item) {
                    $product = Product::findOrFail($item['product_id']);

                    $unitPrice = (float) $product->price_sell;
                    $quantity = (float) $item['quantity'];
                    $lineTotal = round($unitPrice * $quantity, 2);
                    $itemsTotal += $lineTotal;

                    $orderItemsData[] = [
                        'product_id' => $product->id,
                        'quantity'   => $quantity,
                        'unit_price' => $unitPrice,
                        'total'      => $lineTotal,
                    ];

                    // Deduct stock specifically from chosen store
                    if ($validated['store_id']) {
                        $existingStoreStock = DB::table('store_product_stock')
                            ->where('product_id', $product->id)
                            ->where('store_id', $validated['store_id'])
                            ->first();

                        if ($existingStoreStock) {
                            DB::table('store_product_stock')
                                ->where('product_id', $product->id)
                                ->where('store_id', $validated['store_id'])
                                ->decrement('quantity', $quantity);
                        } else {
                            DB::table('store_product_stock')->insert([
                                'product_id' => $product->id,
                                'store_id'   => $validated['store_id'],
                                'quantity'   => max(0, $product->stock_quantity - $quantity),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                        }
                    }

                    // Deduct global product stock
                    $product->decrement('stock_quantity', $quantity);

                    // Track stock movement
                    StockMovement::create([
                        'product_id' => $product->id,
                        'type'       => 'OUT',
                        'quantity'   => $quantity,
                        'source'     => 'WEB_SALE',
                        'store_id'   => $validated['store_id'],
                        'user_id'    => null,
                    ]);
                }

                // Shipping fee calculation: 0.00 DH for PICKUP_STORE, otherwise default 25 DH (or free if >= 300)
                $shippingFee = ($validated['delivery_type'] === 'PICKUP_STORE')
                    ? 0.00
                    : (isset($validated['shipping_fee']) 
                        ? (float) $validated['shipping_fee'] 
                        : ($itemsTotal >= 300 ? 0.00 : 25.00));

                $grandTotal = round($itemsTotal + $shippingFee, 2);

                $phone = $validated['phone'];
                $city = $validated['city'];
                $address = !empty($validated['address'])
                    ? $validated['address']
                    : ($validated['delivery_type'] === 'PICKUP_STORE' ? 'Retrait en Magasin' : 'Livraison à domicile');

                // Create Order with source = 'WEB' and status = 'PENDING'
                $newOrder = Order::create([
                    'customer_name'    => $validated['customer_name'],
                    'phone'            => $phone,
                    'customer_phone'   => $phone,
                    'city'             => $city,
                    'customer_city'    => $city,
                    'address'          => $address,
                    'customer_address' => $address,
                    'store_id'         => $validated['store_id'],
                    'delivery_type'    => $validated['delivery_type'],
                    'source'           => 'WEB',
                    'order_type'       => 'WEB',
                    'status'           => 'PENDING',
                    'payment_method'   => $validated['payment_method'] ?? 'COD',
                    'shipping_fee'     => $shippingFee,
                    'total_amount'     => $grandTotal,
                    'notes'            => $validated['notes'] ?? null,
                ]);

                foreach ($orderItemsData as $itemData) {
                    $newOrder->orderItems()->create($itemData);
                }

                return $newOrder->load(['store', 'orderItems.product']);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Commande enregistrée avec succès !',
                'data'    => $order,
            ], 201);

        } catch (\Exception $e) {
            Log::error('OrderController storeWebOrder Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'Erreur lors de l\'enregistrement de la commande: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Admin & Staff: List all orders with filters (WEB, POS, status)
     * Filters strictly by user's assigned store if not Admin / Super Admin
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = Order::with(['orderItems.product', 'user', 'store']);

        if ($user) {
            $isAdmin = false;
            if (method_exists($user, 'hasRole')) {
                $isAdmin = $user->hasRole('ADMIN') || 
                           $user->hasRole('SUPER_ADMIN') || 
                           $user->hasRole('admin') || 
                           $user->hasRole('super_admin');
            }
            if (!$isAdmin && isset($user->role)) {
                $isAdmin = in_array(strtoupper((string) $user->role), ['ADMIN', 'SUPER_ADMIN']);
            }

            if (!$isAdmin) {
                if ($user->store_id) {
                    $query->where('store_id', $user->store_id);
                } else {
                    return response()->json([
                        'status' => 'success',
                        'data'   => new \Illuminate\Pagination\LengthAwarePaginator([], 0, (int)$request->get('per_page', 20)),
                    ]);
                }
            } elseif ($request->has('store_id') && !empty($request->store_id) && $request->store_id !== 'ALL') {
                $query->where('store_id', $request->store_id);
            }
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        if ($request->has('order_type') && !empty($request->order_type)) {
            $query->where('order_type', $request->order_type);
        } elseif ($request->is('*web-orders*') || $request->has('source')) {
            $source = $request->get('source', 'WEB');
            $query->where(function ($q) use ($source) {
                $q->where('source', $source)->orWhere('order_type', $source);
            });
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_phone', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        $orders = $query->latest()->paginate((int) $request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data'   => $orders,
        ]);
    }

    /**
     * Admin & Caissier: Get all Web E-Commerce Orders
     * Automatically filters by cashier's assigned store if not Admin / Super Admin
     */
    public function getWebOrders(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Order::with(['orderItems.product', 'store', 'user'])
            ->where(function ($q) {
                $q->where('source', 'WEB')
                  ->orWhere('order_type', 'WEB');
            });

        // If logged user is NOT Admin / Super Admin (e.g. Caissier assigned to Store B), filter strictly by their assigned store
        if ($user) {
            $isAdmin = false;
            if (method_exists($user, 'hasRole')) {
                $isAdmin = $user->hasRole('ADMIN') || 
                           $user->hasRole('SUPER_ADMIN') || 
                           $user->hasRole('admin') || 
                           $user->hasRole('super_admin');
            }
            if (!$isAdmin && isset($user->role)) {
                $isAdmin = in_array(strtoupper((string) $user->role), ['ADMIN', 'SUPER_ADMIN']);
            }

            if (!$isAdmin) {
                if ($user->store_id) {
                    $query->where('store_id', $user->store_id);
                } else {
                    // If cashier has no store assigned, return empty list
                    return response()->json([
                        'status' => 'success',
                        'data'   => $request->has('per_page') 
                            ? new \Illuminate\Pagination\LengthAwarePaginator([], 0, (int)$request->get('per_page', 20)) 
                            : [],
                    ]);
                }
            } elseif ($request->has('store_id') && !empty($request->store_id) && $request->store_id !== 'ALL') {
                $query->where('store_id', $request->store_id);
            }
        }

        if ($request->has('status') && !empty($request->status) && $request->status !== 'ALL') {
            $query->where('status', $request->status);
        }

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'LIKE', "%{$search}%")
                  ->orWhere('customer_phone', 'LIKE', "%{$search}%")
                  ->orWhere('phone', 'LIKE', "%{$search}%")
                  ->orWhere('id', 'LIKE', "%{$search}%");
            });
        }

        $orders = $request->has('per_page')
            ? $query->latest()->paginate((int) $request->get('per_page', 20))
            : $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => $orders,
        ]);
    }

    /**
     * Admin & Staff: Show single order details
     */
    public function show(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $isAdmin = false;
            if (method_exists($user, 'hasRole')) {
                $isAdmin = $user->hasRole('ADMIN') || 
                           $user->hasRole('SUPER_ADMIN') || 
                           $user->hasRole('admin') || 
                           $user->hasRole('super_admin');
            }
            if (!$isAdmin && isset($user->role)) {
                $isAdmin = in_array(strtoupper((string) $user->role), ['ADMIN', 'SUPER_ADMIN']);
            }

            if (!$isAdmin && $user->store_id && $order->store_id && (int) $order->store_id !== (int) $user->store_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Accès non autorisé aux commandes d\'un autre magasin.',
                ], 403);
            }
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order->load(['orderItems.product', 'user', 'store']),
        ]);
    }

    /**
     * Admin & Caissier: Update order status (PENDING, PROCESSING, COMPLETED, CANCELLED, DELIVERED)
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $isAdmin = false;
            if (method_exists($user, 'hasRole')) {
                $isAdmin = $user->hasRole('ADMIN') || 
                           $user->hasRole('SUPER_ADMIN') || 
                           $user->hasRole('admin') || 
                           $user->hasRole('super_admin');
            }
            if (!$isAdmin && isset($user->role)) {
                $isAdmin = in_array(strtoupper((string) $user->role), ['ADMIN', 'SUPER_ADMIN']);
            }

            if (!$isAdmin && $user->store_id && $order->store_id && (int) $order->store_id !== (int) $user->store_id) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Action non autorisée sur les commandes d\'un autre magasin.',
                ], 403);
            }
        }

        $validated = $request->validate([
            'status' => ['required', 'string', 'in:PENDING,PROCESSING,COMPLETED,CANCELLED,DELIVERED'],
        ]);

        $order->update(['status' => $validated['status']]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statut mis à jour avec succès !',
            'data'    => $order->fresh()->load(['orderItems.product', 'user', 'store']),
        ]);
    }

    /**
     * Public E-Commerce Order Tracking Endpoint
     * Allows customer to track order status by Order Reference (e.g. OR1872984), numeric ID, or phone number.
     */
    public function trackWebOrder(Request $request, string $identifier): JsonResponse
    {
        $cleanId = trim($identifier);
        $cleanId = ltrim($cleanId, '#');

        // Extract numeric ID if formatted as OR1872980 + ID
        $numericId = null;
        if (preg_match('/^OR(\d+)$/i', $cleanId, $matches)) {
            $fullNum = (int) $matches[1];
            if ($fullNum > 1872980) {
                $numericId = $fullNum - 1872980;
            } else {
                $numericId = $fullNum;
            }
        } elseif (is_numeric($cleanId)) {
            $num = (int) $cleanId;
            if ($num > 1872980) {
                $numericId = $num - 1872980;
            } else {
                $numericId = $num;
            }
        }

        $query = Order::with(['orderItems.product', 'store']);

        $order = null;
        if ($numericId) {
            $order = (clone $query)->where('id', $numericId)->first();
        }

        if (!$order) {
            $order = (clone $query)->where('id', $cleanId)
                ->orWhere('order_number', $cleanId)
                ->orWhere('customer_phone', $cleanId)
                ->orWhere('phone', $cleanId)
                ->first();
        }

        if (!$order) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune commande trouvée pour cette référence.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $order,
        ]);
    }

}
