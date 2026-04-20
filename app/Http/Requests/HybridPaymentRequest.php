<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HybridPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'custom_int5' => ['required', 'integer', 'min:1'],
            'wallet_applied' => ['required', 'numeric', 'min:0'],
            'remaining_amount' => ['required', 'numeric', 'min:0'],
            'type' => ['nullable', 'string', 'in:registration,team'],
        ];
    }

    public function messages(): array
    {
        return [
            'custom_int5.required' => 'Order reference is missing.',
            'custom_int5.integer' => 'Invalid order reference.',
            'wallet_applied.required' => 'Wallet amount is required.',
            'wallet_applied.numeric' => 'Wallet amount must be a number.',
            'wallet_applied.min' => 'Wallet amount cannot be negative.',
            'remaining_amount.required' => 'Remaining payment amount is required.',
            'remaining_amount.numeric' => 'Remaining amount must be a number.',
            'remaining_amount.min' => 'Remaining amount cannot be negative.',
        ];
    }
}
