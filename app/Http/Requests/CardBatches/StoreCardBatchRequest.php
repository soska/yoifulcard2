<?php

namespace App\Http\Requests\CardBatches;

use App\Services\CardBatchIssuer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new batch of preissued cards. CardBatchIssuer checks the count again,
 * with the preissue limit, under its lock. Notes are for superadmins, so only
 * the admin area sends them.
 */
class StoreCardBatchRequest extends FormRequest
{
    public const MAX_NOTES = 5000;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'notes' => is_string($this->input('notes')) && trim($this->input('notes')) === '' ? null : $this->input('notes'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'count' => ['required', 'integer', 'min:1', 'max:'.CardBatchIssuer::MAX_BATCH_SIZE],
            'notes' => ['nullable', 'string', 'max:'.self::MAX_NOTES],
        ];
    }

    public function cardCount(): int
    {
        return (int) $this->validated('count');
    }

    public function notes(): ?string
    {
        $notes = $this->validated('notes');

        return is_string($notes) ? $notes : null;
    }
}
