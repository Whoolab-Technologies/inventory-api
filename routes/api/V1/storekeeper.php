<?php

use App\Http\Controllers\V1\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\StorekeeperAuthController;
use App\Http\Controllers\V1\StoreController;

Route::prefix('storekeeper')->group(function () {
    Route::post('login', [StorekeeperAuthController::class, 'login']);

    Route::middleware('auth:storekeeper')->group(function () {
        Route::post('logout', [StorekeeperAuthController::class, 'logout']);
        Route::get('stores', [StoreController::class, 'getStoresByStorekeeper']);
        Route::get('products', [ProductController::class, 'getProducts']);
    });
});