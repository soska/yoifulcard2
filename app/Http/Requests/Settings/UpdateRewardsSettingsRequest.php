<?php

namespace App\Http\Requests\Settings;

use App\Enums\EarnMode;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Turn rewards on or off and choose how points are earned. Owners and
 * managers of an active organization. Turning rewards off keeps the rule, so
 * turning them on again starts where the business left off.
 */
class UpdateRewardsSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $organization = CurrentOrganization::organization($this);

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rewards_enabled' => ['required', 'boolean'],
            'earn_mode' => [
                Rule::requiredIf(fn () => $this->boolean('rewards_enabled')),
                'nullable',
                Rule::enum(EarnMode::class),
            ],
            'earn_rate' => [
                Rule::requiredIf(fn () => $this->boolean('rewards_enabled') && $this->input('earn_mode') === EarnMode::Spend->value),
                'nullable',
                'decimal:0,2',
                'gt:0',
                'lte:1000',
            ],
        ];
    }

    public function enabled(): bool
    {
        return $this->boolean('rewards_enabled');
    }

    /**
     * The columns to save. A business that turns rewards off keeps its rule.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        if (! $this->enabled()) {
            return ['rewards_enabled' => false];
        }

        $mode = EarnMode::from((string) $this->validated('earn_mode'));

        return [
            'rewards_enabled' => true,
            'earn_mode' => $mode,
            'earn_rate' => $mode === EarnMode::Spend ? (string) $this->validated('earn_rate') : null,
        ];
    }
}
