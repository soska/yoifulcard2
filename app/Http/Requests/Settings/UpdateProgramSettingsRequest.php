<?php

namespace App\Http\Requests\Settings;

use App\Support\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProgramSettingsRequest extends FormRequest
{
    /**
     * Owners and managers of an active organization.
     */
    public function authorize(): bool
    {
        $organization = CurrentOrganization::organization($this);

        return $organization !== null && $this->user()?->can('update', $organization) === true;
    }

    protected function prepareForValidation(): void
    {
        $terms = $this->input('terms_url');

        $this->merge([
            'name' => is_string($this->input('name')) ? trim($this->input('name')) : $this->input('name'),
            'terms_url' => is_string($terms) && trim($terms) !== '' ? trim($terms) : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'terms_url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
        ];
    }
}
