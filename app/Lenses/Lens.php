<?php

namespace App\Lenses;

use App\Metrics\Cards\ValueMetric;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The template of every lens: a table of records seen from one angle, such as the items without a supplier.
 * A lens gives its uriKey, its label, its info, its query, and its columns, and the names of its actions and cards.
 * The address (/lenses/{uriKey}), the permission, and the access check come from here, so a new lens needs no tests of its own.
 */
abstract class Lens
{
    /**
     * The unique key of the lens, in kebab case, such as items-without-supplier. It builds the address and the permission.
     */
    abstract public static function uriKey(): string;

    /**
     * The name of the lens, shown as the page title, the sidebar link, and the permission label.
     */
    abstract public static function label(): string;

    /**
     * What the lens shows, in the info of its page and of its count card.
     */
    abstract public static function explanation(): string;

    /**
     * What to do when the lens has records, in the info under the explanation.
     */
    abstract public static function guidance(): string;

    /**
     * The query of the records the lens shows.
     */
    abstract public function query(): Builder;

    /**
     * The columns of the lens table.
     *
     * @return list<LensColumn>
     */
    abstract public function columns(): array;

    /**
     * The permission to open the lens, built from its uriKey: items-without-supplier becomes lenses.items_without_supplier.
     */
    public static function permission(): string
    {
        return 'lenses.'.Str::snake(Str::camel(static::uriKey()));
    }

    /**
     * The number of records the lens shows, counted from its own query, so a count card always matches the lens table.
     */
    public static function count(): int
    {
        return (new static)->query()->count();
    }

    /**
     * Only a user with the lens permission, or a super user, can open the lens.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('viewLens', static::class);
    }

    /**
     * The names of the actions on each row, which the admin application draws. A lens has none unless it gives them.
     *
     * @return list<string>
     */
    public function recordActions(): array
    {
        return [];
    }

    /**
     * The names of the actions on the selected rows. A lens has none unless it gives them.
     *
     * @return list<string>
     */
    public function bulkActions(): array
    {
        return [];
    }

    /**
     * The cards above the lens table. A lens has none unless it gives them.
     *
     * @return list<class-string<ValueMetric>>
     */
    public function metrics(): array
    {
        return [];
    }
}
