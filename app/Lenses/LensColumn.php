<?php

namespace App\Lenses;

use BackedEnum;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * One column of a lens table: its key (an attribute, or a relation's attribute such as supplier.name_ar), its label,
 * how it is drawn (a text, an image, or a yes or no), whether it sorts and searches, whether it is a number or a badge,
 * how its value is worked out for a record, and the color of its cell.
 */
final class LensColumn
{
    /**
     * The value of the cell, worked out from the record; the record's own attribute when none is given.
     */
    private ?Closure $state = null;

    /**
     * The color of the cell: a fixed one, one worked out from the value and the record, or none.
     */
    private string|Closure|null $color = null;

    private bool $sortable = false;

    private bool $searchable = false;

    private bool $numeric = false;

    private bool $badge = false;

    /**
     * @param  'text'|'image'|'boolean'  $type
     */
    private function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $type,
    ) {}

    public static function text(string $name, string $label): self
    {
        return new self($name, $label, 'text');
    }

    /**
     * A picture stored on the public disk; its cell holds the picture's address.
     */
    public static function image(string $name, string $label): self
    {
        return new self($name, $label, 'image');
    }

    /**
     * A yes or no.
     */
    public static function boolean(string $name, string $label): self
    {
        return new self($name, $label, 'boolean');
    }

    /**
     * The record's ID, sortable, in the primary color when the user can open the record's own page.
     */
    public static function id(): self
    {
        return self::text('id', __('filament/components/id_column.label'))
            ->sortable()
            ->color(fn (mixed $value, Model $record): ?string => Gate::allows('view', $record) ? 'primary' : null);
    }

    public function sortable(): self
    {
        $this->sortable = true;

        return $this;
    }

    public function searchable(): self
    {
        $this->searchable = true;

        return $this;
    }

    public function numeric(): self
    {
        $this->numeric = true;

        return $this;
    }

    public function badge(): self
    {
        $this->badge = true;

        return $this;
    }

    /**
     * Works the value of the cell out from the record, for a column that is not an attribute.
     *
     * @param  Closure(Model): mixed  $state
     */
    public function state(Closure $state): self
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @param  string|(Closure(mixed, Model): ?string)|null  $color
     */
    public function color(string|Closure|null $color): self
    {
        $this->color = $color;

        return $this;
    }

    /**
     * Whether the column sorts the table. A relation's column never does.
     */
    public function isSortable(): bool
    {
        return $this->sortable && ! $this->isOfARelation();
    }

    public function isSearchable(): bool
    {
        return $this->searchable;
    }

    /**
     * Whether the column is a relation's attribute, such as supplier.name_ar.
     */
    public function isOfARelation(): bool
    {
        return str_contains($this->name, '.');
    }

    /**
     * What the browser needs to draw the column: its kind, its label, and whether it sorts and searches.
     *
     * @return array{name: string, label: string, type: string, sortable: bool, searchable: bool, numeric: bool, badge: bool}
     */
    public function definition(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'sortable' => $this->isSortable(),
            'searchable' => $this->searchable,
            'numeric' => $this->type === 'text' && $this->numeric,
            'badge' => $this->type === 'text' && $this->badge,
        ];
    }

    /**
     * One cell for the record: its value (the picture's address for an image) and its color.
     *
     * @return array{value: mixed, color: ?string}
     */
    public function cell(Model $record): array
    {
        $value = $this->state === null ? data_get($record, $this->name) : ($this->state)($record);

        if ($this->type === 'image') {
            return ['value' => is_string($value) && $value !== '' ? Storage::disk('public')->url($value) : null, 'color' => null];
        }

        $color = $this->type === 'text' && $this->color instanceof Closure ? ($this->color)($value, $record) : $this->color;

        return [
            'value' => $value instanceof BackedEnum ? $value->value : $value,
            'color' => $this->type === 'text' && is_string($color) ? $color : null,
        ];
    }
}
