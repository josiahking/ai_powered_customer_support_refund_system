<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::connection()->select('select 1');

    return response()->json([
        'status' => 'ok',
        'database' => 'connected',
    ]);
});
