<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PublicProductController extends Controller
{
    /**
     * Get E-Commerce Active Products Listing
     * Supports Pagination, Category Filtering, Search & Sorting
     */
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'brand'])
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
     * Get Single Product Details by Barcode or ID
     */
    public function show(string $identifier): JsonResponse
    {
        $product = Product::with(['category', 'brand'])
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
            'items.*.quantity'=> ['required', 'integer', 'min:1'],
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
}
