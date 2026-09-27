<?php

namespace Tests\Fakes;

use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundAnalysisInput;
use Illuminate\Support\Facades\DB;
use Throwable;

class FakeRefundAiAnalyzer implements RefundAiAnalyzer
{
    /** @var list<RefundAnalysisInput> */
    public array $inputs = [];

    /** @var list<int> */
    public array $transactionLevels = [];

    private ?Throwable $failure = null;

    public function __construct(private RefundAnalysis $analysis) {}

    public function analyze(RefundAnalysisInput $input): RefundAnalysis
    {
        $this->inputs[] = $input;
        $this->transactionLevels[] = DB::transactionLevel();

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
