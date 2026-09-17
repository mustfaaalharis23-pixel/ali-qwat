<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class OrderIndexRequest extends FormRequest
{
    /**
     * The columns the app is allowed to sort by, and the column each one is in the database.
     */
    public const SORTS = [
        'date' => 'date',
        'amount' => 'total_amount',
    ];

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'sort' => ['nullable', 'string', 'in:'.implode(',', array_keys(self::SORTS))],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /**
     * The database column to sort by; the date when the app does not ask for one.
     */
    public function sortColumn(): string
    {
        return self::SORTS[$this->string('sort')->value()] ?? 'date';
    }

    /**
     * The direction of the sort; newest and biggest first when the app does not ask for one.
     */
    public function sortDirection(): string
    {
        return $this->string('direction')->value() === 'asc' ? 'asc' : 'desc';
    }
}
