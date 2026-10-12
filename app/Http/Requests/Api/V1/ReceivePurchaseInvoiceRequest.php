<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReceivePurchaseInvoiceRequest extends FormRequest
{
    /**
     * The barcode is sent exactly as the scanner read it from the carton label, such as PI-12.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'barcode' => ['required', 'string', 'max:255'],
        ];
    }
}
