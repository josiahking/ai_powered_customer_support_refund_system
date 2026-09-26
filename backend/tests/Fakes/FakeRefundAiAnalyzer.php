<?php

namespace Tests\Fakes;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundAnalysisInput;
use Throwable;

class FakeRefundAiAnalyzer implements RefundAiAnalyzer
{
    /** @var list<RefundAnalysisInput> */
    public array $inputs = [];

    private ?Throwable $failure = null;

    public function __construct(private RefundAnalysis $analysis) {}

    public function analyze(RefundAnalysisInput $input): RefundAnalysis
    {
        $this->inputs[] = $input;

        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->analysis;
    }

    public function returning(RefundAnalysis $analysis): void
    {
        $this->analysis = $analysis;
        $this->failure = null;
    }

    public function failing(Throwable $failure): void
    {
        $this->failure = $failure;
    }
}
