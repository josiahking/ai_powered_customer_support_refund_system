<?php

namespace App\Domain\Refunds;

enum AiErrorCode: string
{
    case ProviderUnavailable = 'PROVIDER_UNAVAILABLE';
    case RateLimited = 'RATE_LIMITED';
    case InvalidResponse = 'INVALID_RESPONSE';
}
