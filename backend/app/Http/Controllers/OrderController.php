<?php

namespace App\Http\Controllers;

use App\Http\Resources\OrderLookupResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function show(string $orderNumber): JsonResponse
    {
        $order = Order::query()->where('order_number', $orderNumber)->firstOrFail();

        return response()->json((new OrderLookupResource($order))->resolve());
    }
}
