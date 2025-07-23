<?php

use App\Http\Controllers\V1\PurchaseRequestController;
use App\Http\Controllers\V1\ReportsController;
use App\Http\Controllers\V1\SupplierController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\StorekeeperAuthController;
use App\Http\Controllers\V1\StorekeeperController;
use App\Http\Controllers\V1\UnitController;
use App\Http\Controllers\V1\ProductController;
use App\Http\Controllers\V1\CommonController;
use App\Http\Controllers\V1\LocationController;

Route::prefix('storekeeper')->group(function () {
    Route::post('login', [StorekeeperAuthController::class, 'login']);
    Route::delete('fcm', [CommonController::class, 'removeFcmToken']);


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
        Route::get('material_requests/{id}/transactions', [StorekeeperController::class, 'getMaterialRequestTransactions']);
        Route::get('material_requests/{id}/prs/{prId}', [StorekeeperController::class, 'getPrDetails']);


        Route::get('transactions', [StorekeeperController::class, 'getTransactions']);
        Route::post('transactions', [StorekeeperController::class, 'createTransaction']);
        Route::post('transactions/{id}', [StorekeeperController::class, 'updateTransaction']);

        Route::get('dispatches', [StorekeeperController::class, 'getInventoryDispatches']);
        Route::post('dispatches', [StorekeeperController::class, 'createInventoryDispatch']);

        Route::get('engineers', [StorekeeperController::class, 'getEngineers']);
        Route::get('engineers/material-returns', [StorekeeperController::class, 'getEngineersMaterialReturns']);
        Route::get('engineers/{id}/returnables', [StorekeeperController::class, 'getReturnableProducts']);
        Route::post('engineers/material-returns', [StorekeeperController::class, 'createEngineerMaterialReturns']);

        Route::get('stores_engineers', [StorekeeperController::class, 'getEngineersAndStores']);

        Route::get('returns', [StorekeeperController::class, 'getMaterialReturns']);
        Route::post('returns', [StorekeeperController::class, 'postMaterialReturns']);
        Route::post('returns/{id}', [StorekeeperController::class, 'updateMaterialReturns']);

        Route::get('summary', [ReportsController::class, 'consumptionReport']);
        Route::post('summary/export', [ReportsController::class, 'exportReport']);
        Route::post('stocks', [StorekeeperController::class, 'getAvailableStock']);


        Route::get('locations', [LocationController::class, 'index']);
        Route::post('locations', [LocationController::class, 'store']);
        Route::get('locations/{id?}', [LocationController::class, 'show']);
        Route::put('locations/{id}', [LocationController::class, 'update']);
        Route::delete('locations/{id}', [LocationController::class, 'destroy']);
        Route::post('fcm', [CommonController::class, 'saveFcmToken']);
        Route::post('suppliers', [SupplierController::class, 'index']);



        Route::prefix('prs')->group(function () {
            Route::get('/', [PurchaseRequestController::class, 'index']);
            Route::get('/{id}', [PurchaseRequestController::class, 'show']);
            Route::put('/{id}', [PurchaseRequestController::class, 'update']);
            Route::post('/{id}/lpos', [PurchaseRequestController::class, 'createLpo']);
            Route::get('/{id}/on-hold-shipments', [PurchaseRequestController::class, 'getOnHoldShipments']);
            Route::post('/{id}/complete-on-hold-transactions', [PurchaseRequestController::class, 'completeOnHoldShipments']);

        });
        Route::prefix('lpos')->group(function () {
            Route::get('/', [PurchaseRequestController::class, 'getLpos']);
            Route::get('/{id}', [PurchaseRequestController::class, 'getLpo']);
            Route::put('/{id}', [PurchaseRequestController::class, 'storeLpoShipment']);
        });
        Route::prefix('shipments')->group(function () {
            Route::get('/', [PurchaseRequestController::class, 'getShipments']);
            Route::get('/{id}', [PurchaseRequestController::class, 'getShipment']);
            Route::post('/{id}', [PurchaseRequestController::class, 'updateShipment']);
            //  Route::put('/{id}', [PurchaseRequestController::class, 'storeLpoShipment']);
        });

    });
});