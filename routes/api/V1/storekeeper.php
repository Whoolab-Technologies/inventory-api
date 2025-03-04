<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\StorekeeperAuthController;
use App\Http\Controllers\V1\StorekeeperController;
use App\Http\Controllers\V1\StoreController;
use App\Http\Controllers\V1\UnitController;

Route::prefix('storekeeper')->group(function () {
    Route::post('login', [StorekeeperAuthController::class, 'login']);

    Route::middleware('auth:storekeeper')->group(function () {
        Route::post('logout', [StorekeeperAuthController::class, 'logout']);
        Route::get('stores', [StoreController::class, 'getStoresByStorekeeper']);
        Route::get('products', [StorekeeperController::class, 'getProducts']);
        Route::get('home', [StorekeeperController::class, 'getDashboardData']);
        Route::get('units', [UnitController::class, 'index']);
    });
});