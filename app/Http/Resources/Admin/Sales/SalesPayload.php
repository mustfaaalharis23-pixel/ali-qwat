<?php

namespace App\Http\Resources\Admin\Sales;

use App\Enums\Contracts\HasColor;
use App\Enums\Contracts\HasLabel;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Models\Item;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * The small pieces every sales screen sends the same way: a badge enum, whether the user may see what the items cost us,
 * and the public link of a stored file.
 */
final class SalesPayload
{
    /**
     * An enum as a badge: its value, its label in the user's language, and its color.
     *
     * @return array{value: string, label: string, color: string|null}|null
     */
    public static function enum(?\BackedEnum $case): ?array
    {
        if ($case === null) {
            return null;
        }

        return [
            'value' => (string) $case->value,
            'label' => $case instanceof HasLabel ? (string) $case->getLabel() : (string) $case->value,
            'color' => $case instanceof HasColor ? (is_string($color = $case->getColor()) ? $color : null) : null,
        ];
    }

    /**
     * Whether the signed-in user may see what the items cost us, and so the profit worked out from it (item.view_cost_price).
     */
    public static function seesCost(): bool
    {
        $attributes = request()->attributes;

        if (! $attributes->has('sales_sees_cost')) {
            $attributes->set('sales_sees_cost', Gate::allows('viewCostPrice', Item::class));
        }

        return (bool) $attributes->get('sales_sees_cost');
    }

    /**
     * What the signed-in user may do with a record (ListsRecords::abilitiesOf). The sales policies decide by the user's
     * permissions and whether the record is deleted, never by the record's own data, so the answer is read once per kind of record
     * and state, and a list does not ask the permissions table once per row.
     *
     * @return array{view: bool, update: bool, delete: bool, restore: bool, force_delete: bool}
     */
    public static function abilities(Model $record): array
    {
        $trashed = method_exists($record, 'trashed') && $record->trashed();
        $key = 'sales_abilities|'.implode('|', [auth()->id(), $record::class, $trashed ? 'trashed' : 'active']);
        $attributes = request()->attributes;

        if (! $attributes->has($key)) {
            $attributes->set($key, ListsRecords::abilitiesOf($record));
        }

        return $attributes->get($key);
    }

    /**
     * The public link of a file on the public disk, or null when there is none.
     */
    public static function fileUrl(?string $path): ?string
    {
        return filled($path) ? Storage::disk('public')->url($path) : null;
    }
}
