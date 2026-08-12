<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ShopProductController extends Controller
{
    /**
     * Get E-Commerce Products with Store Breakdown (Public Shop API)
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
     * Index alias for storefront
     */
    public function index(Request $request): JsonResponse
    {
        return app(PublicProductController::class)->index($request);
    }

    /**
     * Show single product details
     */
    public function show(string $identifier): JsonResponse
    {
        return app(PublicProductController::class)->show($identifier);
    }
}
