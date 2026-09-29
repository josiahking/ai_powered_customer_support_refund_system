<?php

namespace App\Http\Controllers;

use App\Domain\Refunds\RefundReason;
use App\Http\Requests\StoreRefundRequest;
use App\Http\Resources\RefundRequestDetailResource;
use App\Http\Resources\RefundRequestSummaryResource;
use App\Models\Order;
use App\Models\RefundRequest as RefundRequestModel;
use App\Services\OrderAccessToken;
use App\Services\RefundRequestService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class RefundRequestController extends Controller
{
    public function index(): JsonResponse
    {
        $requests = RefundRequestModel::query()
            ->with(['customer:id,name', 'order:id,order_number,item_name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => RefundRequestSummaryResource::collection($requests)->resolve(),
        ]);
    }

    public function show(RefundRequestModel $refundRequest): JsonResponse
    {
        $refundRequest->load(['customer:id,name', 'order:id,order_number,item_name,total_amount,ordered_at,final_sale']);

        return response()->json((new RefundRequestDetailResource($refundRequest))->resolve());
    }

    public function store(
        StoreRefundRequest $request,
        OrderAccessToken $tokens,
        RefundRequestService $refundRequestService,
    ): JsonResponse {
        $claims = $tokens->claims($request->validated('order_access_token'));
        $order = $claims === null ? null : Order::query()
            ->whereKey($claims['order_id'])
            ->where('customer_id', $claims['customer_id'])
            ->first();

        if ($order === null) {
            return response()->json([
                'message' => 'Order verification is invalid or has expired. Verify your order again.',
            ], Response::HTTP_FORBIDDEN);
        }

        $refundRequest = $refundRequestService->create(
            orderId: $order->id,
            customerId: $order->customer_id,
            requestedAmount: (string) $request->validated('requested_amount'),
            reason: RefundReason::from($request->validated('reason')),
            customerMessage: $request->validated('customer_message'),
        );

        return response()->json([
            'id' => $refundRequest->id,
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
