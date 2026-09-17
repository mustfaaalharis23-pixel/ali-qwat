<?php

namespace App\Filament\Lenses;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * The template of every lens: a page with a table of records seen from one angle, such as the items without a supplier.
 * A lens gives its uriKey, its label, its query, and its columns. The address, the permission, the sidebar group,
 * and the access check come from here, so a new lens needs no tests of its own.
 */
abstract class Lens extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEye;

    /**
     * Higher than every other sidebar item, so the Lenses group comes last, after System administration.
     */
    protected static ?int $navigationSort = 100;

    /**
     * The unique key of the lens, in kebab case, such as items-without-supplier. It builds the address and the permission.
     */
    abstract public static function uriKey(): string;

    /**
     * The name of the lens, shown as the page title, the sidebar link, and the permission label.
     */
    abstract public static function label(): string;

    /**
     * What the lens shows, in the info modal of its count card.
     */
    abstract public static function explanation(): string;

    /**
     * What to do when the lens has records, in the info modal of its count card under the explanation.
     */
    abstract public static function guidance(): string;

    /**
     * The query of the records the lens shows.
     */
    abstract public function query(): Builder;

    /**
     * The columns of the lens table.
     *
     * @return list<Column>
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

    public static function getSlug(?Panel $panel = null): string
    {
        return 'lenses/'.static::uriKey();
    }

    public static function getNavigationGroup(): string
    {
        return __('filament/navigation.groups.lenses');
    }

    public static function getNavigationLabel(): string
    {
        return static::label();
    }

    public function getTitle(): string|Htmlable
    {
        return static::label();
    }

    /**
     * The actions on each row of the lens table. A lens has none unless it gives them.
     *
     * @return list<Action>
     */
    public function recordActions(): array
    {
        return [];
    }

    /**
     * The actions above the lens table, such as bulk actions on the selected rows. A lens has none unless it gives them.
     *
     * @return list<Action>
     */
    public function toolbarActions(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->query())
            ->columns($this->columns())
            ->recordActions($this->recordActions())
            ->toolbarActions($this->toolbarActions());
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }
}
