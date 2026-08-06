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

Route::prefix('v1')->group(function () {
    
    // Public Settings Route (POS Ticket & Storefront)
    Route::get('/settings', [SettingController::class, 'show']);

    // Public E-Commerce Routes (Next.js Storefront)
    Route::prefix('shop')->group(function () {
        Route::get('/products', [PublicProductController::class, 'index']);
        Route::get('/products/{identifier}', [PublicProductController::class, 'show']);
        Route::get('/categories', [PublicProductController::class, 'categories']);
        Route::get('/brands', [BrandController::class, 'index']);
        Route::get('/faqs', [FaqController::class, 'index']);
        Route::get('/posts', [PostController::class, 'index']);
        Route::post('/check-stock', [PublicProductController::class, 'checkStock']);
    });

    // Public Auth Routes
    Route::post('/login', [AuthController::class, 'login']);

    // Protected Routes (Require Bearer Token)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Accessible by MAGASINIER & ADMIN (For Stock Entry selection)
        Route::get('/suppliers/list', [SupplierController::class, 'index']);

        // Shared Barcode Search (Accessible by ADMIN, CAISSIER, and MAGASINIER)
        Route::get('/stock/barcode/{barcode}', [StockController::class, 'findByBarcode']);

        // Magasinier Stock Scanning In
        Route::middleware('role:MAGASINIER|ADMIN')->prefix('stock')->group(function () {
            Route::post('/scan-in', [StockController::class, 'scanIn']);
            Route::get('/history', [StockController::class, 'history']);
        });

        // POS Sales Routes (CAISSIER & ADMIN only)
        Route::middleware('role:CAISSIER|ADMIN')->prefix('pos')->group(function () {
            Route::post('/checkout', [PosController::class, 'checkout']);
        });

        // Admin Only Routes
        Route::middleware('role:ADMIN')->prefix('admin')->group(function () {
            Route::get('/dashboard/stats', [AdminDashboardController::class, 'stats']);
            Route::get('/dashboard-stats', [AdminDashboardController::class, 'stats']);
            Route::get('/products/generate-barcode', [AdminProductController::class, 'generateUniqueBarcode']);
            Route::get('/suppliers/purchases-history', [StockController::class, 'supplierHistory']);
            Route::get('/sales/history', [PosController::class, 'salesHistory']);
            Route::post('/settings', [SettingController::class, 'update']);
            Route::apiResource('products', AdminProductController::class);
            Route::apiResource('users', UserController::class);
            Route::apiResource('brands', BrandController::class);
            Route::apiResource('suppliers', SupplierController::class);
            Route::apiResource('categories', CategoryController::class);
            Route::apiResource('faqs', FaqController::class);
            Route::apiResource('posts', PostController::class);
        });
    });

});
