<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', 'in:income,expense'],
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'description' => ['required', 'string', 'max:500'],
            'transaction_date' => ['sometimes', 'date', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reference' => ['sometimes', 'nullable', 'string', 'max:50'],
            'metadata' => ['sometimes', 'nullable', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.min' => 'Amount must be at least 1 (in minor units, e.g., 1000 = 100 toman for IRR).',
            'transaction_date.before_or_equal' => 'Transaction date cannot be in the future.',
        ];
    }
}
