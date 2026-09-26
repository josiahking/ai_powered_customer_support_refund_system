<?php

namespace App\Domain\Refunds;

enum AiAnalysisStatus: string
{
    case NotAnalyzed = 'NOT_ANALYZED';
    case Analyzed = 'ANALYZED';
    case Unavailable = 'UNAVAILABLE';
    case RateLimited = 'RATE_LIMITED';
    case InvalidResponse = 'INVALID_RESPONSE';
}
