<?php

namespace App\Http\Requests\Ledger;

use App\Models\Card;
use App\Services\CardLedger;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A correction: a signed, nonzero amount with up to two decimals, and a
 * required note.
 */
class AdjustBalanceRequest extends FormRequest
{
    /**
     * Only members of the card's organization, while it is active. This runs
     * before validation, so outsiders get a 403 whatever they send.
     */
    public function authorize(): bool
    {
        $card = $this->route('card');

        return $card instanceof Card && $this->user()?->can('transact', $card) === true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'required',
                'decimal:0,2',
                'gte:-'.CardLedger::MAX_BALANCE,
                'lte:'.CardLedger::MAX_BALANCE,
                function (string $attribute, mixed $value, Closure $fail): void {
                    $amount = trim((string) $value);

                    if (is_numeric($amount) && bccomp($amount, '0', 2) === 0) {
                        $fail(__('The adjustment cannot be 0.'));
                    }
                },
            ],
            'note' => ['required', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required' => __('A note is required for adjustments.'),
        ];
    }

    public function amount(): string
    {
        return trim((string) $this->validated('amount'));
    }

    public function note(): string
    {
        return (string) $this->validated('note');
    }
}
