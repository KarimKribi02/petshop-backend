<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\StockController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PublicProductController;
use App\Http\Controllers\Api\V1\AdminDashboardController;
use App\Http\Controllers\Api\V1\AdminProductController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\BrandController;
use App\Http\Controllers\Api\V1\SupplierController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\FaqController;
use App\Http\Controllers\Api\V1\PostController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\StoreController;
use App\Http\Controllers\Api\V1\ShopProductController;

Route::prefix('v1')->group(function () {
    
    // Public Settings Route (POS Ticket & Storefront)
    Route::get('/settings', [SettingController::class, 'show']);

    // Public E-Commerce Routes (Next.js Storefront)
    Route::prefix('shop')->group(function () {
        Route::get('/products', [PublicProductController::class, 'index']);
        Route::get('/products-stock', [PublicProductController::class, 'getShopProducts']);
        Route::get('/products/{identifier}', [PublicProductController::class, 'show']);
        Route::get('/categories', [PublicProductController::class, 'categories']);
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/faqs', [FaqController::class, 'index']);
        Route::get('/posts', [PostController::class, 'index']);
        Route::get('/stores', [StoreController::class, 'index']);
        Route::post('/check-stock', [PublicProductController::class, 'checkStock']);
        Route::post('/orders', [OrderController::class, 'storeWebOrder']);
    });
    Route::get('/shop-products', [ShopProductController::class, 'getShopProducts']);
    Route::post('/orders', [OrderController::class, 'storeWebOrder']);

    // Public Auth Routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected Routes (Require Bearer Token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Shared Barcode Search (Accessible by ADMIN, CAISSIER, and MAGASINIER)
        Route::get('/stock/barcode/{barcode}', [StockController::class, 'findByBarcode']);

        // 📦 Shared Routes for ADMIN & MAGASINIER (Stock, Suppliers CRUD & Purchases History)
        Route::middleware('role:ADMIN|MAGASINIER')->group(function () {
            // 1. Specific Custom Routes FIRST (Must be placed BEFORE apiResource to prevent {supplier} param collision)
            Route::get('/suppliers/purchases-history', [StockController::class, 'supplierHistory']);
            Route::get('/admin/suppliers/purchases-history', [StockController::class, 'supplierHistory']);
            Route::get('/suppliers/list', [SupplierController::class, 'index']);
            Route::get('/admin/suppliers', [SupplierController::class, 'index']);
            Route::post('/admin/suppliers', [SupplierController::class, 'store']);
            Route::get('/admin/suppliers/{supplier}', [SupplierController::class, 'show']);
            Route::put('/admin/suppliers/{supplier}', [SupplierController::class, 'update']);
            Route::delete('/admin/suppliers/{supplier}', [SupplierController::class, 'destroy']);

            // 2. Resource Routes AFTER specific custom endpoints
            Route::apiResource('suppliers', SupplierController::class);

            // Stock Scanning & History
            Route::prefix('stock')->group(function () {
                Route::post('/scan-in', [StockController::class, 'scanIn']);
                Route::get('/history', [StockController::class, 'history']);
            });
        });

        // POS Sales Routes (CAISSIER & ADMIN only)
        Route::middleware('role:CAISSIER|ADMIN')->prefix('pos')->group(function () {
            Route::get('/products', [PosController::class, 'getPosProducts']);
            Route::post('/checkout', [PosController::class, 'checkout']);
        });

        // Shared endpoints for ADMIN and CAISSIER (Clôture de Caisse & Sales History & Web Orders)
        Route::middleware('role:ADMIN|CAISSIER')->group(function () {
            Route::get('/sales/history', [PosController::class, 'salesHistory']);
            Route::get('/admin/sales/history', [PosController::class, 'salesHistory']);
            Route::get('/staff/list', [UserController::class, 'index']);
            Route::get('/web-orders', [OrderController::class, 'getWebOrders']);
            Route::get('/admin/web-orders', [OrderController::class, 'getWebOrders']);
            Route::get('/orders', [OrderController::class, 'index']);
            Route::get('/admin/orders', [OrderController::class, 'index']);
            Route::get('/orders/{order}', [OrderController::class, 'show']);
            Route::get('/admin/orders/{order}', [OrderController::class, 'show']);
            Route::patch('/orders/{order}/status', [OrderController::class, 'updateStatus']);
            Route::patch('/admin/orders/{order}/status', [OrderController::class, 'updateStatus']);
        });

        // Stores list (accessible by all authenticated staff)
        Route::get('/stores', [StoreController::class, 'index']);
        Route::get('/stores/list', [StoreController::class, 'index']);

        // Admin Only Routes
        Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
            Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);
            Route::get('/dashboard-stats', [DashboardController::class, 'getStats']);
            Route::get('/products/generate-barcode', [AdminProductController::class, 'generateUniqueBarcode']);
            Route::post('/settings', [SettingController::class, 'update']);
            Route::apiResource('stores', StoreController::class);
            Route::apiResource('products', AdminProductController::class);
            Route::apiResource('users', UserController::class);
            Route::apiResource('brands', BrandController::class);
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('faqs', FaqController::class);
            Route::apiResource('posts', PostController::class);
        });
    });

});
