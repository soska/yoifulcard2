<?php

namespace App\Http\Requests\Ledger;

use App\Models\Card;
use App\Services\CardLedger;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Add funds or charge: a positive amount with up to two decimals, and an
 * optional note. CardLedger checks the amount again with bcmath.
 */
class LedgerAmountRequest extends FormRequest
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
            'amount' => ['required', 'decimal:0,2', 'gt:0', 'lte:'.CardLedger::MAX_BALANCE],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function amount(): string
    {
        return trim((string) $this->validated('amount'));
    }

    public function note(): ?string
    {
        $note = $this->validated('note');

        return is_string($note) ? $note : null;
    }
}
