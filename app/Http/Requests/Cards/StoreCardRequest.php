<?php

namespace App\Http\Requests\Cards;

use App\Services\CardLedger;
use App\Support\Decimal;
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
     *
     * @return numeric-string
     */
    public function initialBalance(): string
    {
        return Decimal::of($this->validated('initial_balance'));
    }
}
