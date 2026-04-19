<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefundMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'method' => ['required', 'in:wallet,bank'],
            'account_name' => ['required_if:method,bank', 'nullable', 'string', 'max:255'],
            'bank_name' => ['required_if:method,bank', 'nullable', 'string', 'max:255'],
            'account_number' => ['required_if:method,bank', 'nullable', 'string', 'max:50'],
            'branch_code' => ['required_if:method,bank', 'nullable', 'string', 'max:20'],
            'account_type' => ['required_if:method,bank', 'nullable', 'in:cheque,savings,business'],
        ];
    }

    public function messages(): array
    {
        return [
            'method.required' => 'Please select a refund method.',
            'method.in' => 'Refund method must be wallet or bank transfer.',
            'account_name.required_if' => 'Account holder name is required for bank refunds.',
            'bank_name.required_if' => 'Bank name is required for bank refunds.',
            'account_number.required_if' => 'Account number is required for bank refunds.',
            'branch_code.required_if' => 'Branch code is required for bank refunds.',
            'account_type.required_if' => 'Account type is required for bank refunds.',
        ];
    }
}
