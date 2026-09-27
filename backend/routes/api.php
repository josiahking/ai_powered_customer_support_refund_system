<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\RefundRequestController;
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
Route::get('/refund-requests', [RefundRequestController::class, 'index']);
Route::get('/refund-requests/{refundRequest}', [RefundRequestController::class, 'show']);
Route::post('/refund-requests', [RefundRequestController::class, 'store']);
