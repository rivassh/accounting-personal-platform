<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', 'string', 'in:bank,cash,credit_card,wallet,investment'],
            'currency' => ['sometimes', 'string', 'in:IRR,USD,EUR,GBP'],
            'initial_balance' => ['sometimes', 'integer', 'min:0'],
            'bank_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'account_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'card_number' => ['sometimes', 'nullable', 'string', 'max:50'],
            'color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'icon' => ['sometimes', 'nullable', 'string', 'max:50'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.in' => 'Account type must be one of: bank, cash, credit_card, wallet, investment.',
            'currency.in' => 'Currency must be one of: IRR, USD, EUR, GBP.',
            'color.regex' => 'Color must be a valid hex color code (e.g., #1d4ed8).',
        ];
    }
}
