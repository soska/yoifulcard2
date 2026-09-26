<?php

namespace App\Http\Requests\Cards;

use Closure;
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
                'lte:99999999.99',
                // A nonzero initial balance is posted as a ledger load, which
                // arrives with the ledger. Until then only zero is accepted.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_numeric($value) && bccomp((string) $value, '0', 2) !== 0) {
                        $fail(__('The initial balance must be 0 for now.'));
                    }
                },
            ],
            'email' => ['nullable', 'string', 'email', 'max:255'],
        ];
    }
}
