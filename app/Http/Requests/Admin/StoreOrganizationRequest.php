<?php

namespace App\Http\Requests\Admin;

use App\Models\Organization;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    /** Lowercase letters and numbers in groups joined by single hyphens. */
    public const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * The superadmin middleware already guards the route.
     */
    public function authorize(): bool
    {
        return $this->user()?->isSuperadmin() === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'slug' => is_string($this->input('slug')) ? mb_strtolower(trim($this->input('slug'))) : $this->input('slug'),
            'owner_email' => is_string($this->input('owner_email')) ? mb_strtolower(trim($this->input('owner_email'))) : $this->input('owner_email'),
            'owner_name' => is_string($this->input('owner_name')) ? trim($this->input('owner_name')) : $this->input('owner_name'),
            'card_limit' => $this->input('card_limit') === '' ? null : $this->input('card_limit'),
            'plan_notes' => is_string($this->input('plan_notes')) && trim($this->input('plan_notes')) === '' ? null : $this->input('plan_notes'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:100', 'regex:'.self::SLUG_PATTERN, Rule::unique(Organization::class, 'slug')],
            'owner_email' => ['required', 'string', 'email', 'max:255'],
            'owner_name' => ['nullable', 'string', 'max:255'],
            ...UpdateOrganizationPlanRequest::planRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => __('Use only lowercase letters, numbers, and single hyphens.'),
            'slug.unique' => __('An organization with this slug already exists.'),
        ];
    }
}
