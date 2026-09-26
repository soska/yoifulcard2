<?php

namespace App\Http\Requests\Cards;

use App\Services\CardLedger;
use Illuminate\Foundation\Http\FormRequest;

class StoreCardRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'initial_balance' => [
                'required',
                'decimal:0,2',
                'gte:0',
                'lte:'.CardLedger::MAX_BALANCE,
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }

    /**
     * The initial balance as a decimal string.
     */
    public function initialBalance(): string
    {
        return trim((string) $this->validated('initial_balance'));
    }
}
