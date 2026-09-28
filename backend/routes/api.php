<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\RefundRequestController;
use App\Http\Controllers\SupportAuthController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::connection()->select('select 1');

    return response()->json([
        'status' => 'ok',
        'database' => 'connected',
    ]);
});

Route::get('/orders/{orderNumber}', [OrderController::class, 'show']);
Route::post('/refund-requests', [RefundRequestController::class, 'store']);

Route::post('/support/login', [SupportAuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/support/logout', [SupportAuthController::class, 'logout']);

Route::middleware('support.auth')->group(function (): void {
    Route::get('/support/session', [SupportAuthController::class, 'session']);
    Route::get('/refund-requests', [RefundRequestController::class, 'index']);
    Route::get('/refund-requests/{refundRequest}', [RefundRequestController::class, 'show']);
});
