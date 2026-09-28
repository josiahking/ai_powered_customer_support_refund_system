<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Throwable;

class SupportAuthToken
{
    public function issue(string $subject): string
    {
        $payload = [
            'purpose' => 'support-auth',
            'subject' => $subject,
            'expires_at' => now()->addMinutes((int) config('support.auth_ttl_minutes'))->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function isValid(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        return is_array($payload)
            && ($payload['purpose'] ?? null) === 'support-auth'
            && ($payload['subject'] ?? null) === config('support.username')
            && is_int($payload['expires_at'] ?? null)
            && $payload['expires_at'] > now()->timestamp;
    }
}
