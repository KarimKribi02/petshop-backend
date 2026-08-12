<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PublicProductController extends Controller
{
    /**
     * Get E-Commerce Active Products Listing
     * Supports Pagination, Category Filtering, Search & Sorting
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand', 'stores'])
            ->where('is_active', true);

        // 1. Filter by Category ID
        if ($request->has('category_id') && $request->category_id != '') {
            $query->where('category_id', $request->category_id);
        }

        // Filter by Brand ID
        if ($request->has('brand_id') && $request->brand_id != '') {
            $query->where('brand_id', $request->brand_id);
        }

        // 2. Search by Title or Barcode
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('barcode', 'LIKE', "%{$searchTerm}%");
            });
        }

        // 3. Sorting (price_asc, price_desc, latest)
        $sortBy = $request->get('sort_by', 'latest');
        switch ($sortBy) {
            case 'price_asc':
                $query->orderBy('price_sell', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('price_sell', 'desc');
                break;
            case 'latest':
            default:
                $query->latest();
                break;
        }

        // Paginate results (Default 12 items per page for E-commerce grid)
        $perPage = $request->get('per_page', 12);
        $products = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $products,
        ]);
    }

    /**
     * Get E-Commerce Products with detailed Store Stocks Breakdown
     * Returns breakdown of stock per active store
     */
    public function getShopProducts(Request $request): JsonResponse
    {
        $products = Product::where('is_active', true)
            ->with(['category', 'brand', 'stores'])
            ->get()
            ->map(function ($product) {
                // Get stock breakdown across all active stores
                $storeStocks = DB::table('store_product_stock')
                    ->join('stores', 'store_product_stock.store_id', '=', 'stores.id')
                    ->where('store_product_stock.product_id', $product->id)
                    ->select(
                        'stores.id as store_id',
                        'stores.name as store_name',
                        'store_product_stock.quantity'
                    )
                    ->get();

                return [
                    'id'           => $product->id,
                    'title'        => $product->title,
                    'barcode'      => $product->barcode,
                    'price_sell'   => $product->price_sell,
                    'unit_type'    => $product->unit_type ?? 'PIECE',
                    'image'        => $product->image,
                    'image_url'    => $product->image ? url('storage/' . $product->image) : null,
                    'total_stock'  => (float) ($product->stock_quantity ?? 0),
                    'quantity'     => (float) ($product->stock_quantity ?? 0),
                    'stock_quantity' => (float) ($product->stock_quantity ?? 0),
                    'category'     => $product->category,
                    'brand'        => $product->brand,
                    'stores_stock' => $storeStocks, // 👈 Breakdown: [{store_id, store_name, quantity}]
                ];
            });

        return response()->json([
            'status' => 'success',
            'data'   => $products,
        ]);
    }

    /**
     * Get Single Product Details by Barcode or ID
     */
    public function show(string $identifier): JsonResponse
    {
        $product = Product::with(['category', 'brand', 'stores'])
            ->where('is_active', true)
            ->where(function ($q) use ($identifier) {
                $q->where('barcode', $identifier)
                  ->orWhere('id', $identifier);
            })
            ->first();

        if (!$product) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Produit non trouvé ou indisponible.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $product,
        ]);
    }

    /**
     * Get Categories List for E-Commerce Navigation Header/Sidebar
     */
    public function categories(): JsonResponse
    {
        $categories = Category::withCount(['products' => function ($q) {
            $q->where('is_active', true);
        }])->get();

        return response()->json([
            'status' => 'success',
            'data'   => $categories,
        ]);
    }

    /**
     * Real-time Stock Check for Next.js Cart Validation
     * Batch check multiple items stock availability before checkout
     */
    public function checkStock(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'items'           => ['required', 'array', 'min:1'],
            'items.*.barcode' => ['required', 'string'],
            'items.*.quantity'=> ['required', 'numeric', 'min:0.01'],
        ]);

        $stockStatus = [];
        $isAllAvailable = true;

        foreach ($validated['items'] as $item) {
            $product = Product::select('id', 'barcode', 'title', 'stock_quantity', 'price_sell')
                ->where('barcode', $item['barcode'])
                ->where('is_active', true)
                ->first();

            if (!$product) {
                $stockStatus[] = [
                    'barcode'         => $item['barcode'],
                    'requested_qty'   => $item['quantity'],
                    'available_stock' => 0,
                    'is_available'    => false,
                    'message'         => 'Produit introuvable.',
                ];
                $isAllAvailable = false;
                continue;
            }

            $available = $product->stock_quantity >= $item['quantity'];
            if (!$available) {
                $isAllAvailable = false;
            }

            $stockStatus[] = [
                'product_id'      => $product->id,
                'barcode'         => $product->barcode,
                'title'           => $product->title,
                'requested_qty'   => $item['quantity'],
                'available_stock' => $product->stock_quantity,
                'is_available'    => $available,
                'price_sell'      => $product->price_sell,
            ];
        }

        return response()->json([
            'status'           => 'success',
            'is_all_available' => $isAllAvailable,
            'items'            => $stockStatus,
        ]);
    }

    /**
     * Store E-Commerce Web Order (Cash On Delivery / Moroccan COD Checkout)
     * Creates PENDING order record, links items, decrements stock atomically
     */
    public function storeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name'    => ['required', 'string', 'max:255'],
            'customer_phone'   => ['required', 'string', 'max:50'],
            'customer_city'    => ['required', 'string', 'max:100'],
            'customer_address' => ['required', 'string', 'max:1000'],
            'notes'            => ['nullable', 'string', 'max:1000'],
            'payment_method'   => ['nullable', 'string', 'in:COD,CASH,CARD,ONLINE'],
            'items'            => ['required', 'array', 'min:1'],
            'items.*.barcode'  => ['nullable', 'string'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);

        try {
            $orderData = DB::transaction(function () use ($validated) {
                $totalAmount = 0;
                $orderItemsToInsert = [];
                $stockMovementsToInsert = [];

                foreach ($validated['items'] as $item) {
                    $query = Product::where('is_active', true)->lockForUpdate();
                    if (!empty($item['barcode'])) {
                        $product = $query->where('barcode', $item['barcode'])->first();
                    } elseif (!empty($item['product_id'])) {
                        $product = $query->where('id', $item['product_id'])->first();
                    } else {
                        throw new \Exception("Chaque article doit avoir un code-barres ou un ID valide.");
                    }

                    if (!$product) {
                        throw new \Exception("Produit indisponible ou introuvable.");
                    }

                    if ($product->stock_quantity < $item['quantity']) {
                        throw new \Exception("Stock insuffisant pour {$product->title}. Disponible: {$product->stock_quantity}");
                    }

                    $itemPrice = (float) $product->price_sell;
                    $subtotal = $itemPrice * (float) $item['quantity'];
                    $totalAmount += $subtotal;

                    // Decrement stock
                    $product->decrement('stock_quantity', $item['quantity']);

                    $orderItemsToInsert[] = [
                        'product_id' => $product->id,
                        'quantity'   => $item['quantity'],
                        'unit_price' => $itemPrice,
                    ];

                    $stockMovementsToInsert[] = [
                        'product_id' => $product->id,
                        'user_id'    => null, // Web Order
                        'type'       => 'OUT',
                        'quantity'   => $item['quantity'],
                        'source'     => 'WEB_SALE',
                        'notes'      => "Commande Web E-Commerce - {$validated['customer_name']} ({$validated['customer_phone']})",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                // Create Order record with PENDING status for admin review
                $order = Order::create([
                    'user_id'          => null,
                    'order_type'       => 'WEB',
                    'total_amount'     => $totalAmount,
                    'status'           => 'PENDING',
                    'payment_method'   => $validated['payment_method'] ?? 'COD',
                    'customer_name'    => $validated['customer_name'],
                    'customer_phone'   => $validated['customer_phone'],
                    'customer_city'    => $validated['customer_city'],
                    'customer_address' => $validated['customer_address'],
                    'notes'            => $validated['notes'] ?? null,
                ]);

                foreach ($orderItemsToInsert as $itemData) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'product_id' => $itemData['product_id'],
                        'quantity'   => $itemData['quantity'],
                        'unit_price' => $itemData['unit_price'],
                    ]);
                }

                if (!empty($stockMovementsToInsert)) {
                    StockMovement::insert($stockMovementsToInsert);
                }

                return $order->load(['orderItems.product']);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Votre commande a été passée avec succès! Notre équipe vous contactera pour confirmation.',
                'data'    => [
                    'order_id' => $orderData->id,
                    'order'    => $orderData,
                ],
            ], 201);

        } catch (\Exception $e) {
            Log::error('Web Storefront Order Error: ' . $e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
