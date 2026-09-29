<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Crypt;
use Throwable;

class OrderAccessToken
{
    public function ttlMinutes(): int
    {
        return (int) config('order_access.ttl_minutes', 15);
    }

    public function issue(Order $order): string
    {
        return Crypt::encryptString(json_encode([
            'purpose' => 'refund-order-access',
            'order_id' => (int) $order->id,
            'customer_id' => (int) $order->customer_id,
            'expires_at' => now()->addMinutes($this->ttlMinutes())->timestamp,
        ], JSON_THROW_ON_ERROR));
    }

    /** @return array{order_id: int, customer_id: int}|null */
    public function claims(?string $token): ?array
    {
        if ($token === null || $token === '') {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($payload)
            || count($payload) !== 4
            || ($payload['purpose'] ?? null) !== 'refund-order-access'
            || ! is_int($payload['order_id'] ?? null) || $payload['order_id'] < 1
            || ! is_int($payload['customer_id'] ?? null) || $payload['customer_id'] < 1
            || ! is_int($payload['expires_at'] ?? null) || $payload['expires_at'] <= now()->timestamp) {
            return null;
        }

        return [
            'order_id' => $payload['order_id'],
            'customer_id' => $payload['customer_id'],
        ];
    }
}
