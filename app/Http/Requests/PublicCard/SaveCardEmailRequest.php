<?php

namespace App\Http\Requests\PublicCard;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The cardholder's "get balance updates" form. Anyone with the card link may
 * post it; the route is rate limited.
 */
class SaveCardEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
        ];
    }

    public function email(): string
    {
        return $this->validated('email');
    }
}
