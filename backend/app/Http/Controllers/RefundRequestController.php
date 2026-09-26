<?php

namespace App\Http\Controllers;

use App\Domain\Refunds\RefundReason;
use App\Http\Requests\StoreRefundRequest;
use App\Services\RefundRequestService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RefundRequestController extends Controller
{
    public function store(StoreRefundRequest $request, RefundRequestService $refundRequestService): JsonResponse
    {
        $refundRequest = $refundRequestService->create(
            orderId: (int) $request->validated('order_id'),
            requestedAmount: (string) $request->validated('requested_amount'),
            reason: RefundReason::from($request->validated('reason')),
            customerMessage: $request->validated('customer_message'),
        );

        return response()->json([
            'id' => $refundRequest->id,
            'customer_id' => $refundRequest->customer_id,
            'order_id' => $refundRequest->order_id,
            'outcome' => $refundRequest->status->value,
            'reason_code' => $refundRequest->resolution_reason_code,
            'explanation' => $refundRequest->resolution_explanation,
            'ai_analysis_status' => $refundRequest->ai_status->value,
            'ai_analysis' => $refundRequest->ai_analysis,
            'ai_provider' => $refundRequest->ai_provider,
            'ai_model' => $refundRequest->ai_model,
            'policy' => [
                'outcome' => $refundRequest->policy_outcome?->value,
                'reason_code' => $refundRequest->policy_reason_code->value,
                'explanation' => $refundRequest->policy_explanation,
            ],
        ], Response::HTTP_CREATED);
    }
}
