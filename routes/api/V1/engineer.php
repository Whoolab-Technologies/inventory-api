<?php

use App\Http\Controllers\V1\CommonController;
use App\Http\Controllers\V1\EngineerAuthController;
use App\Http\Controllers\V1\EngineerController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\ProductController;

Route::prefix('engineer')->group(function () {
    Route::post('login', [EngineerAuthController::class, 'login']);
    Route::delete('fcm', [CommonController::class, 'removeFcmToken']);

    Route::middleware('auth:engineer')->group(function () {
        Route::post('logout', [EngineerAuthController::class, 'logout']);
        Route::get('stores', [EngineerController::class, 'getStores']);

        Route::get('products', [EngineerController::class, 'getProducts']);
        Route::get('products/{id}', [ProductController::class, 'getProduct']);

        Route::get('mr', [EngineerController::class, 'getMaterialRequest']);
        Route::post('mr', [EngineerController::class, 'createMaterialRequest']);
        Route::get('home', [EngineerController::class, 'getDashboardData']);

        Route::get('transactions', [EngineerController::class, 'getTransactions']);

        Route::get('material-returns', [EngineerController::class, 'getMaterialReturns']);

        Route::get('stores_engineers', [EngineerController::class, 'getEngineersAndStores']);
        Route::post('fcm', [CommonController::class, 'saveFcmToken']);
        Route::get('materials', [CommonController::class, 'getProducts']);
    });
});