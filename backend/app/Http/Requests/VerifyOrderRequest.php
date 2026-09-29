<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'order_number' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:254'],
        ];
    }
}
