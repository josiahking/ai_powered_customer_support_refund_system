<?php

namespace App\Http\Controllers;

use App\Http\Requests\VerifyOrderRequest;
use App\Http\Resources\OrderLookupResource;
use App\Models\Order;
use App\Services\OrderAccessToken;
use Illuminate\Http\JsonResponse;
use Throwable;

class OrderController extends Controller
{
    public function verify(VerifyOrderRequest $request, OrderAccessToken $tokens): JsonResponse
    {
        $email = mb_strtolower(trim($request->validated('email')));
        $orderNumber = mb_strtoupper(trim($request->validated('order_number')));
        $order = Order::query()
            ->with('customer:id,email')
            ->where('order_number', $orderNumber)
            ->whereHas('customer', fn ($query) => $query->whereRaw('LOWER(TRIM(email)) = ?', [$email]))
            ->first();

        if ($order === null) {
            return response()->json([
                'message' => 'We could not verify that order. Check the order number and email and try again.',
            ], 404);
        }

        try {
            $accessToken = $tokens->issue($order);
        } catch (Throwable) {
            return response()->json([
                'message' => 'Order verification is temporarily unavailable. Try again.',
            ], 503);
        }

        return response()->json([
            'order' => (new OrderLookupResource($order))->resolve(),
            'order_access_token' => $accessToken,
            'expires_in_minutes' => $tokens->ttlMinutes(),
        ]);
    }
}
