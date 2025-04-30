<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\StorekeeperAuthController;
use App\Http\Controllers\V1\StorekeeperController;
use App\Http\Controllers\V1\StoreController;
use App\Http\Controllers\V1\UnitController;
use App\Http\Controllers\V1\ProductController;

Route::prefix('storekeeper')->group(function () {
    Route::post('login', [StorekeeperAuthController::class, 'login']);

    Route::middleware('auth:storekeeper')->group(function () {
        Route::post('logout', [StorekeeperAuthController::class, 'logout']);
        Route::get('stores', [StorekeeperController::class, 'getStores']);
        Route::get('home', [StorekeeperController::class, 'getDashboardData']);
        Route::get('units', [UnitController::class, 'index']);

        Route::get('products', [StorekeeperController::class, 'getProducts']);
        Route::post('products', [ProductController::class, 'store']);
        Route::get('products/{id}', [ProductController::class, 'getProduct']);

        Route::get('material_requests', [StorekeeperController::class, 'getMaterialRequests']);
        Route::post('material_requests/{id}', [StorekeeperController::class, 'updateMaterialrequest']);


        Route::get('transactions', [StorekeeperController::class, 'getTransactions']);
        Route::post('transactions/{id}', [StorekeeperController::class, 'updateTransaction']);

        Route::get('dispatches', [StorekeeperController::class, 'getInventoryDispatches']);
        Route::post('dispatches', [StorekeeperController::class, 'createInventoryDispatch']);

        Route::get('engineers', [StorekeeperController::class, 'getEngineers']);

        Route::get('stores_engineers', [StorekeeperController::class, 'getEngineersAndStores']);

        Route::get('returns', [StorekeeperController::class, 'getMaterialReturns']);
        Route::post('returns', [StorekeeperController::class, 'postMaterialReturns']);
        Route::post('returns/{id}', [StorekeeperController::class, 'updateMaterialReturns']);

    });
});