<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\V1\CommonController;

Route::post('password/reset', [CommonController::class, 'sendResetLinkEmail']);
Route::post('password/confirm', [CommonController::class, 'resetPassword']);
Route::post('notification', [CommonController::class, 'testNotification']);
