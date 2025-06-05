<?php


use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\EngineerController;
use App\Http\Controllers\V1\ReportsController;
use App\Http\Controllers\V1\StockController;

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\AdminAuthController;
use App\Http\Controllers\V1\StorekeeperController;
use App\Http\Controllers\V1\StoreController;
use App\Http\Controllers\V1\BrandController;
use App\Http\Controllers\V1\UnitController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CategoriesController;

Route::prefix('admin')->group(function () {
    Route::post('register', [AdminAuthController::class, 'register']);
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware('auth:admin')->group(function () {
        Route::post('logout', [AdminAuthController::class, 'logout']);

        Route::post('admins', [AdminAuthController::class, 'register']);
        Route::get('admins', [AdminAuthController::class, 'index']);
        Route::get('admins/{id}', [AdminAuthController::class, 'show']);
        Route::put('admins/{id}', [AdminAuthController::class, 'update']);
        Route::delete('admins/{id}', [AdminAuthController::class, 'destroy']);

        Route::post('storekeepers', [StorekeeperController::class, 'store']);
        Route::get('storekeepers', [StorekeeperController::class, 'index']);
        Route::get('storekeepers/{id}', [StorekeeperController::class, 'show']);
        Route::put('storekeepers/{id}', [StorekeeperController::class, 'update']);
        Route::delete('storekeepers/{id}', [StorekeeperController::class, 'destroy']);

        Route::post('stores', [StoreController::class, 'store']);
        Route::get('stores', [StoreController::class, 'index']);
        Route::get('stores/{id}', [StoreController::class, 'show']);
        Route::put('stores/{id}', [StoreController::class, 'update']);
        Route::delete('stores/{id}', [StoreController::class, 'destroy']);
        Route::get('storekeepers/{storekeeper_id}/stores', [StoreController::class, 'getStoresByStorekeeper']);

        // Add brand-related routes
        Route::post('brands', [BrandController::class, 'store']);
        Route::get('brands', [BrandController::class, 'index']);
        Route::get('brands/{id}', [BrandController::class, 'show']);
        Route::put('brands/{id}', [BrandController::class, 'update']);
        Route::delete('brands/{id}', [BrandController::class, 'destroy']);

        // Add unit-related routes
        Route::post('units', [UnitController::class, 'store']);
        Route::get('units', [UnitController::class, 'index']);
        Route::get('units/{id}', [UnitController::class, 'show']);
        Route::put('units/{id}', [UnitController::class, 'update']);
        Route::delete('units/{id}', [UnitController::class, 'destroy']);

        // Add Product-related routes
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products', [ProductController::class, 'index']);
        Route::get('products/{id}', [ProductController::class, 'show']);
        Route::put('products/{id}', [ProductController::class, 'update']);
        Route::delete('products/{id}', [ProductController::class, 'destroy']);


        // Add Enginner-related routes
        Route::post('engineers', [EngineerController::class, 'store']);
        Route::get('engineers', [EngineerController::class, 'index']);
        Route::get('engineers/{id}', [EngineerController::class, 'show']);
        Route::put('engineers/{id}', [EngineerController::class, 'update']);
        Route::delete('engineers/{id}', [EngineerController::class, 'destroy']);

        // Add Enginner-related routes
        Route::post('stocks', [StockController::class, 'store']);
        Route::get('stocks', [StockController::class, 'index']);
        Route::get('stocks/{id}', [StockController::class, 'show']);
        Route::put('stocks/{id}', [StockController::class, 'update']);
        Route::delete('stocks/{id}', [StockController::class, 'destroy']);

        Route::get('stocks/{id}/transactions', [StockController::class, 'getTransactions']);

        // Add Department-related routes
        Route::get('departments', [DepartmentController::class, 'index']);
        Route::post('departments', [DepartmentController::class, 'store']);
        Route::put('departments/{id}', [DepartmentController::class, 'update']);
        Route::delete('departments/{id}', [DepartmentController::class, 'destroy']);

        // Add Categories-related routes
        Route::get('categories', [CategoriesController::class, 'index']);
        Route::post('categories', [CategoriesController::class, 'store']);
        Route::put('categories/{id}', [CategoriesController::class, 'update']);
        Route::delete('categories/{id}', [CategoriesController::class, 'destroy']);

        // Add Categories-related routes
        Route::get('brands', [BrandController::class, 'index']);
        Route::post('brands', [BrandController::class, 'store']);
        Route::put('brands/{id}', [BrandController::class, 'update']);
        Route::delete('brands/{id}', [BrandController::class, 'destroy']);

        Route::prefix('reports')->group(function () {
            Route::get('material-in-out', [ReportsController::class, 'transactionReport']);
            Route::get('material-returns', [ReportsController::class, 'materialReturnReport']);

        });
    });
});