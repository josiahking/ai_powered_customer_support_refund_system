<?php

namespace App\Http\Requests;

use App\Domain\Refunds\RefundReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'order_access_token' => ['required', 'string', 'max:8192'],
            'order_id' => ['prohibited'],
            'customer_id' => ['prohibited'],
            'requested_amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'reason' => ['required', Rule::enum(RefundReason::class)],
            'customer_message' => ['required', 'string', 'max:2000'],
        ];
    }
}
