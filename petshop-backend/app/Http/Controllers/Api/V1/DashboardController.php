<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    /**
     * Complete Admin Dashboard Stats
     * Strict & Distinct Separation for POS Caisse vs Web E-Commerce
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $storeId = $request->get('store_id');
            $filterStore = !empty($storeId) && $storeId !== 'ALL';

            // 1. Base Order Query
            $baseOrderQuery = Order::query();
            if ($filterStore) {
                $baseOrderQuery->where('store_id', $storeId);
            }

            // Overall Completed Totals
            $totalRevenue = (float) (clone $baseOrderQuery)->where('status', 'COMPLETED')->sum('total_amount');
            $totalOrders  = (int) (clone $baseOrderQuery)->count();
            $ordersToday  = (int) (clone $baseOrderQuery)->whereDate('created_at', now()->today())->count();
            $todayRevenue = (float) (clone $baseOrderQuery)->where('status', 'COMPLETED')
                ->whereDate('created_at', now()->today())
                ->sum('total_amount');

            // 🏪 2. STRICT POS Sales Calculation (Caisse Terminal)
            $posQuery = (clone $baseOrderQuery)->where(function ($q) {
                $q->where('source', 'POS')
                  ->orWhere('order_type', 'POS')
                  ->orWhereNull('source');
            });

            $posRevenue     = (float) (clone $posQuery)->where('status', 'COMPLETED')->sum('total_amount');
            $posOrdersCount = (int) (clone $posQuery)->count();

            // 🌐 3. STRICT Web E-Commerce Sales Calculation (Storefront)
            $webQuery = (clone $baseOrderQuery)->where(function ($q) {
                $q->where('source', 'WEB')
                  ->orWhere('order_type', 'WEB');
            });

            $webRevenue     = (float) (clone $webQuery)->where('status', 'COMPLETED')->sum('total_amount');
            $webOrdersCount = (int) (clone $webQuery)->count();

            // 📦 4. Products & Stock Stats
            $stockCol = Schema::hasColumn('products', 'quantity') ? 'quantity' : 'stock_quantity';
            $alertCol = Schema::hasColumn('products', 'alert_stock_level') ? 'alert_stock_level' : 'min_stock_alert';

            $totalProducts = (int) Product::count();
            $lowStockCount = (int) Product::whereColumn($stockCol, '<=', $alertCol)->count();

            // 5. Low stock products list
            $lowStockProducts = Product::whereColumn($stockCol, '<=', $alertCol)->take(10)->get();

            // 6. Top selling products
            $topProducts = OrderItem::selectRaw('product_id, SUM(quantity) as total_sold, SUM(COALESCE(total, quantity * unit_price)) as total_revenue')
                ->groupBy('product_id')
                ->with('product:id,title')
                ->orderByDesc('total_sold')
                ->take(5)
                ->get()
                ->map(fn($item) => [
                    'title'         => $item->product?->title ?? 'Produit',
                    'total_sold'    => (float) $item->total_sold,
                    'total_revenue' => (float) $item->total_revenue,
                ]);

            // 7. Recent Orders & Sales by Channel
            $salesByChannel = [
                [
                    'order_type' => 'POS',
                    'count'      => $posOrdersCount,
                    'total'      => $posRevenue,
                ],
                [
                    'order_type' => 'WEB',
                    'count'      => $webOrdersCount,
                    'total'      => $webRevenue,
                ],
            ];

            $recentOrders = (clone $baseOrderQuery)->with(['orderItems.product:id,title,barcode,price_sell', 'user:id,name'])
                ->latest()
                ->take(10)
                ->get()
                ->map(function ($ord) {
                    return [
                        'id'            => $ord->id,
                        'customer_name' => $ord->customer_name ?: ($ord->user ? $ord->user->name : 'Client Boutique'),
                        'phone'         => $ord->phone ?: ($ord->customer_phone ?: 'N/A'),
                        'total_amount'  => (float) $ord->total_amount,
                        'status'        => $ord->status,
                        'source'        => $ord->source ?: 'POS',
                        'order_type'    => $ord->order_type ?: ($ord->source ?: 'POS'),
                        'created_at'    => $ord->created_at,
                        'order_items'   => $ord->orderItems,
                        'user'          => $ord->user,
                    ];
                });

            $statsData = [
                'totalRevenue'   => $totalRevenue,
                'todayRevenue'   => $todayRevenue,
                'totalOrders'    => $totalOrders,
                'ordersToday'    => $ordersToday,
                'totalProducts'  => $totalProducts,
                'lowStockCount'  => $lowStockCount,
                
                // 👈 Separated & Corrected Metrics
                'posRevenue'     => $posRevenue,
                'posOrdersCount' => $posOrdersCount,
                'webRevenue'     => $webRevenue,
                'webOrdersCount' => $webOrdersCount,

                // Snake_case aliases for all UI components
                'total_revenue'          => $totalRevenue,
                'today_revenue'          => $todayRevenue,
                'total_orders'           => $totalOrders,
                'today_orders'           => $ordersToday,
                'total_products'         => $totalProducts,
                'low_stock_alerts_count' => $lowStockCount,
            ];

            return response()->json([
                'status' => 'success',
                'data'   => [
                    'stats'                 => $statsData,
                    'kpis'                  => $statsData,
                    'sales_by_channel'      => $salesByChannel,
                    'recent_orders'         => $recentOrders,
                    'low_stock_products'    => $lowStockProducts,
                    'low_stock_alerts'      => $lowStockProducts,
                    'top_products'          => $topProducts,
                    'top_selling_products'  => $topProducts,
                ]
            ]);
        } catch (\Throwable $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Alias for stats() method
     */
    public function stats(Request $request): JsonResponse
    {
        return $this->getStats($request);
    }

    /**
     * Alias for dashboardStats() method
     */
    public function dashboardStats(Request $request): JsonResponse
    {
        return $this->getStats($request);
    }
}
