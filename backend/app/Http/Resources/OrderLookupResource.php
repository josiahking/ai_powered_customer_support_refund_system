<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderLookupResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'order_number' => $this->order_number,
            'item_name' => $this->item_name,
            'total_amount' => $this->total_amount,
            'ordered_at' => $this->ordered_at->toISOString(),
            'final_sale' => $this->final_sale,
        ];
    }
}
