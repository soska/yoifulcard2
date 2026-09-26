<?php

namespace App\Http\Requests\Settings;

use App\Models\Organization;
use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    /** Largest logo accepted, in kilobytes. */
    public const LOGO_MAX_KB = 2048;

    /**
     * Owners and managers of an active organization.
     */
    public function authorize(): bool
    {
        $organization = $this->organization();

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'currency' => is_string($this->input('currency')) ? strtoupper(trim($this->input('currency'))) : $this->input('currency'),
            'primary_color' => is_string($this->input('primary_color')) ? strtolower(trim($this->input('primary_color'))) : $this->input('primary_color'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $current = $this->organization()?->currency;

        return [
            'name' => ['required', 'string', 'max:255'],
            'primary_color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/'],
            'currency' => ['required', 'string', Rule::in(array_values(array_unique(array_filter([...Organization::CURRENCIES, $current]))))],
            'timezone' => ['required', 'string', 'timezone:all'],
            // Raster images only: an SVG could carry script.
            'logo' => ['nullable', File::image()->types(['png', 'jpg', 'jpeg', 'webp'])->max(self::LOGO_MAX_KB)],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'primary_color.regex' => __('The brand color must be a hex color such as #1e40af.'),
        ];
    }

    public function removeLogo(): bool
    {
        return $this->boolean('remove_logo');
    }

    public function organization(): ?Organization
    {
        return CurrentOrganization::organization($this);
    }
}
