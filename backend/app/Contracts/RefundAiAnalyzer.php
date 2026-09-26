<?php

namespace App\Contracts;

use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundAnalysisInput;

interface RefundAiAnalyzer
{
    public function analyze(RefundAnalysisInput $input): RefundAnalysis;
}
