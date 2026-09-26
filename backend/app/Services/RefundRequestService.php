<?php

namespace App\Services;

use App\Contracts\Ai\Exceptions\LlmInvalidResponseException;
use App\Contracts\Ai\Exceptions\LlmRateLimitedException;
use App\Contracts\Ai\Exceptions\LlmUnavailableException;
use App\Contracts\RefundAiAnalyzer;
use App\Domain\Refunds\AiAnalysisStatus;
use App\Domain\Refunds\AiErrorCode;
use App\Domain\Refunds\Money;
use App\Domain\Refunds\RefundAnalysisInput;
use App\Domain\Refunds\RefundDecision;
use App\Domain\Refunds\RefundOutcome;
use App\Domain\Refunds\RefundPolicyEngine;
use App\Domain\Refunds\RefundPolicyInput;
use App\Domain\Refunds\RefundPolicyReasonCode;
use App\Domain\Refunds\RefundReason;
use App\Models\Order;
use App\Models\RefundRequest;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RefundRequestService
{
    public function __construct(
        private readonly RefundPolicyEngine $policyEngine,
        private readonly RefundAiAnalyzer $aiAnalyzer,
    ) {}

    public function create(
        int $orderId,
        string $requestedAmount,
        RefundReason $reason,
        string $customerMessage,
    ): RefundRequest {
        return DB::transaction(function () use ($orderId, $requestedAmount, $reason, $customerMessage): RefundRequest {
            $order = Order::query()->findOrFail($orderId);
            $requestedMoney = Money::fromDecimal($requestedAmount);

            if ($requestedMoney->cents > Money::fromDecimal($order->total_amount)->cents) {
                throw ValidationException::withMessages([
                    'requested_amount' => 'The refund amount cannot exceed the order total.',
                ]);
            }

            $input = new RefundPolicyInput(
                orderedAt: DateTimeImmutable::createFromInterface($order->ordered_at),
                evaluatedAt: DateTimeImmutable::createFromInterface(now()),
                finalSale: $order->final_sale,
                requestedAmountCents: $requestedMoney->cents,
                reason: RefundReason::Other,
            );

            $aiStatus = AiAnalysisStatus::Analyzed;
            $aiErrorCode = null;
            $analysis = null;

            try {
                $analysis = $this->aiAnalyzer->analyze(new RefundAnalysisInput(
                    customerMessage: $customerMessage,
                    customerReasonHint: $reason,
                    itemName: $order->item_name,
                    requestedAmount: $requestedMoney->toDecimal(),
                    orderAmount: $order->total_amount,
                ));

                $decision = $this->policyEngine->evaluate(new RefundPolicyInput(
                    orderedAt: $input->orderedAt,
                    evaluatedAt: $input->evaluatedAt,
                    finalSale: $input->finalSale,
                    requestedAmountCents: $input->requestedAmountCents,
                    reason: $analysis->classifiedReason,
                    suspicious: $analysis->suspicious,
                    conflictingClaims: $analysis->conflictingClaims,
                ));

                $finalOutcome = $decision->outcome;
                $resolutionReasonCode = $decision->reasonCode->value;
                $resolutionExplanation = $decision->explanation;
            } catch (LlmRateLimitedException) {
                $aiStatus = AiAnalysisStatus::RateLimited;
                $aiErrorCode = AiErrorCode::RateLimited;
                $decision = $this->policyEngine->evaluate($input);
                [$finalOutcome, $resolutionReasonCode, $resolutionExplanation] = $this->resolveAnalysisFailure(
                    $decision,
                    'AI_ANALYSIS_RATE_LIMITED',
                    'The analysis service is rate limited.',
                );
            } catch (LlmInvalidResponseException) {
                $aiStatus = AiAnalysisStatus::InvalidResponse;
                $aiErrorCode = AiErrorCode::InvalidResponse;
                $decision = $this->policyEngine->evaluate($input);
                [$finalOutcome, $resolutionReasonCode, $resolutionExplanation] = $this->resolveAnalysisFailure(
                    $decision,
                    'AI_ANALYSIS_INVALID_RESPONSE',
                    'The analysis service returned an invalid result.',
                );
            } catch (LlmUnavailableException) {
                $aiStatus = AiAnalysisStatus::Unavailable;
                $aiErrorCode = AiErrorCode::ProviderUnavailable;
                $decision = $this->policyEngine->evaluate($input);
                [$finalOutcome, $resolutionReasonCode, $resolutionExplanation] = $this->resolveAnalysisFailure(
                    $decision,
                    'AI_ANALYSIS_UNAVAILABLE',
                    'The analysis service is unavailable.',
                );
            }

            return RefundRequest::query()->create([
                'customer_id' => $order->customer_id,
                'order_id' => $order->id,
                'requested_amount' => $requestedMoney->toDecimal(),
                'reason' => $reason,
                'customer_message' => $customerMessage,
                'status' => $finalOutcome,
                'policy_reason_code' => $decision->reasonCode,
                'policy_explanation' => $decision->explanation,
                'policy_outcome' => $decision->outcome,
                'resolution_reason_code' => $resolutionReasonCode,
                'resolution_explanation' => $resolutionExplanation,
                'ai_status' => $aiStatus,
                'ai_analysis' => $analysis?->toArray(),
                'ai_provider' => config('ai.provider'),
                'ai_model' => config('ai.model'),
                'ai_error_code' => $aiErrorCode?->value,
            ]);
        });
    }

    /** @return array{0: RefundOutcome, 1: string, 2: string} */
    private function resolveAnalysisFailure(
        RefundDecision $policyDecision,
        string $failureReasonCode,
        string $failureExplanation,
    ): array {
        if (in_array($policyDecision->reasonCode, [
            RefundPolicyReasonCode::FinalSale,
            RefundPolicyReasonCode::RefundWindowExpired,
            RefundPolicyReasonCode::HighValueReview,
        ], true)) {
            return [
                $policyDecision->outcome,
                $policyDecision->reasonCode->value,
                $policyDecision->explanation,
            ];
        }

        return [
            RefundOutcome::Escalated,
            $failureReasonCode,
            $failureExplanation.' Human review is required; policy fallback was '.$policyDecision->reasonCode->value.'.',
        ];
    }
}
