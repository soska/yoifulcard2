<?php

namespace App\Http\Requests\Rewards;

use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Create or edit a reward: a name and a cost in points. Owners and managers
 * of an active organization. An edit may also switch the reward on or off.
 */
class SaveRewardRequest extends FormRequest
{
    /** The most points a reward can cost. */
    public const MAX_COST = 1000000;

    public function authorize(): bool
    {
        $organization = CurrentOrganization::organization($this);

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    protected function prepareForValidation(): void
    {
        $name = $this->input('name');

        $this->merge([
            'name' => is_string($name) ? trim($name) : $name,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'points_cost' => ['required', 'integer', 'min:1', 'max:'.self::MAX_COST],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The columns to save. A new reward is active unless told otherwise.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'points_cost' => (int) $this->validated('points_cost'),
            ...($this->has('is_active') ? ['is_active' => $this->boolean('is_active')] : []),
        ];
    }
}
