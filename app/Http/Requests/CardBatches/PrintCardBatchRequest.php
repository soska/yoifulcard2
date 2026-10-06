<?php

namespace App\Http\Requests\CardBatches;

use App\Enums\CardTemplate;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Print a batch as a PDF with one of the templates in code. Whether the
 * template is available to the area asking is checked by CardBatchPrinter.
 */
class PrintCardBatchRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'template' => ['required', 'string', Rule::enum(CardTemplate::class)],
        ];
    }

    public function template(): CardTemplate
    {
        return CardTemplate::from((string) $this->validated('template'));
    }
}
