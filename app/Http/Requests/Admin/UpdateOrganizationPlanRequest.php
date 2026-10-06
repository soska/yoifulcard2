<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateOrganizationPlanRequest extends FormRequest
{
    public const MAX_CARD_LIMIT = 1000000;

    public const MAX_PLAN_NOTES = 5000;

    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'card_limit' => $this->input('card_limit') === '' ? null : $this->input('card_limit'),
            'preissue_limit' => $this->input('preissue_limit') === '' ? null : $this->input('preissue_limit'),
            'plan_notes' => is_string($this->input('plan_notes')) && trim($this->input('plan_notes')) === '' ? null : $this->input('plan_notes'),
        ]);
    }

    /**
     * An empty card limit means unlimited. The preissue limit caps how many
     * unactivated cards the business holds at once; empty means unlimited,
     * 0 means none.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public static function planRules(): array
    {
        return [
            'card_limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_CARD_LIMIT],
            'preissue_limit' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_CARD_LIMIT],
            'plan_notes' => ['nullable', 'string', 'max:'.self::MAX_PLAN_NOTES],
        ];
    }

    /**
     * can_preissue lets the business create card batches itself; a missing
     * value (an unchecked box) turns it off.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...self::planRules(),
            'can_preissue' => ['nullable', 'boolean'],
        ];
    }
}
