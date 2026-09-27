<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RefundRequestSummaryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'requested_amount' => $this->requested_amount,
            'outcome' => $this->status->value,
            'reason_code' => $this->resolution_reason_code,
            'ai_analysis_status' => $this->ai_status->value,
            'created_at' => $this->created_at->toISOString(),
            'customer' => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
            ],
            'order' => [
                'id' => $this->order->id,
                'order_number' => $this->order->order_number,
                'item_name' => $this->order->item_name,
            ],
        ];
    }
}
