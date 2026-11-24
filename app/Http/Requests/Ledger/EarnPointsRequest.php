<?php

namespace App\Http\Requests\Ledger;

use App\Models\Card;
use App\Services\CardLedger;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Award points at the counter. In spend mode the purchase amount is needed
 * (up to two decimals); in visits mode there is nothing to enter. CardLedger
 * checks the program's rule and the amount again.
 */
class EarnPointsRequest extends FormRequest
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
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'decimal:0,2', 'gt:0', 'lte:'.CardLedger::MAX_BALANCE],
        ];
    }

    public function amount(): ?string
    {
        $amount = $this->validated('amount');

        return is_string($amount) && trim($amount) !== '' ? trim($amount) : null;
    }
}
