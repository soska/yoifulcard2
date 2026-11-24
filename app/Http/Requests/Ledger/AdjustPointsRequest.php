<?php

namespace App\Http\Requests\Ledger;

use App\Models\Card;
use App\Services\CardLedger;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A points correction: a signed, nonzero whole number and a required note.
 * Owners and managers only (CardPolicy::adjustPoints).
 */
class AdjustPointsRequest extends FormRequest
{
    /**
     * This runs before validation, so outsiders and employees get a 403
     * whatever they send.
     */
    public function authorize(): bool
    {
        $card = $this->route('card');

        return $card instanceof Card && $this->user()?->can('adjustPoints', $card) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'points' => [
                'required',
                'integer',
                'not_in:0',
                'between:-'.CardLedger::MAX_POINTS.','.CardLedger::MAX_POINTS,
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
            'points.not_in' => __('The points adjustment cannot be 0.'),
        ];
    }

    public function points(): int
    {
        return (int) $this->validated('points');
    }

    public function note(): string
    {
        return (string) $this->validated('note');
    }
}
