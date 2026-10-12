<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\InvoiceStage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseInvoiceIndexRequest extends FormRequest
{
    /**
     * @return array<string, list<string|ValidationRule|object>>
     */
    public function rules(): array
    {
        return [
            'stage' => ['nullable', 'string', Rule::enum(InvoiceStage::class)],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * The direction of the sort by date; newest first when the app does not ask for one.
     */
    public function sortDirection(): string
    {
        return $this->string('direction')->value() === 'asc' ? 'asc' : 'desc';
    }
}
