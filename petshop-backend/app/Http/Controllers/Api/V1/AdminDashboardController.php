<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    /**
     * Get Complete Admin Dashboard Overview
     * KPI Stats, Low Stock Alerts, Top Sellers, Recent Activity
     */
    public function stats(Request $request): JsonResponse
    {
        // 1. Core KPIs
        $totalRevenue = Order::where('status', 'COMPLETED')->sum('total_amount');
        $todayRevenue = Order::where('status', 'COMPLETED')
            ->whereDate('created_at', now()->today())
            ->sum('total_amount');
            
        $totalOrdersCount = Order::where('status', 'COMPLETED')->count();
        $todayOrdersCount = Order::where('status', 'COMPLETED')
            ->whereDate('created_at', now()->today())
            ->count();

        $totalProductsCount = Product::count();
        
        // Low Stock Count (where stock <= min_stock_alert)
        $lowStockCount = Product::whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->where('is_active', true)
            ->count();

        // 2. Low Stock Products List (Alerts)
        $lowStockProducts = Product::with('category:id,name')
            ->whereColumn('stock_quantity', '<=', 'min_stock_alert')
            ->where('is_active', true)
            ->select('id', 'category_id', 'barcode', 'title', 'stock_quantity', 'min_stock_alert', 'price_sell')
            ->orderBy('stock_quantity', 'asc')
            ->take(10)
            ->get();

        // 3. Top 5 Selling Products (Aggregated from OrderItems)
        $topSellingProducts = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_sold'), DB::raw('SUM(quantity * unit_price) as total_revenue'))
            ->groupBy('product_id')
            ->orderByDesc('total_sold')
            ->take(5)
            ->with(['product:id,title,barcode,price_sell'])
            ->get();

        // 4. Breakdown by Order Source (POS vs WEB)
        $salesByChannel = Order::where('status', 'COMPLETED')
            ->select('order_type', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('order_type')
            ->get();

        // 5. Recent Stock Movements Audit Trail
        $recentMovements = StockMovement::with(['product:id,title,barcode', 'user:id,name'])
            ->latest()
            ->take(8)
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'kpis' => [
                    'total_revenue'        => (float) $totalRevenue,
                    'today_revenue'        => (float) $todayRevenue,
                    'total_orders'         => $totalOrdersCount,
                    'today_orders'         => $todayOrdersCount,
                    'total_products'       => $totalProductsCount,
                    'low_stock_alerts_count'=> $lowStockCount,
                ],
                'low_stock_alerts'    => $lowStockProducts,
                'top_selling_products'=> $topSellingProducts,
                'sales_by_channel'    => $salesByChannel,
                'recent_movements'    => $recentMovements,
            ],
        ]);
    }
}
