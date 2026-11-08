<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Filament\Lenses\Lens;
use App\Filament\Widgets\Metrics\ValueMetric;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Filament\Actions\Action;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every lens of the panel (app/Filament/Lenses) through one endpoint: /admin-api/lenses/{uriKey}.
 * The lens itself decides everything, as it did on the panel: who may open it (canAccess), its query, and its columns.
 * The answer is a page of its rows, each cell worked out by the lens's own column, with the column definitions,
 * the names of its row and bulk actions, and its header cards. A new lens needs nothing here.
 */
class LensController extends Controller
{
    use ListsRecords;

    public function show(Request $request, string $key): JsonResponse
    {
        $class = $this->lensOf($key);
        abort_if($class === null, 404);
        abort_unless($class::canAccess(), 403);

        $lens = new $class;
        $lens->bootedInteractsWithTable();

        /** @var array<string, Column> $columns */
        $columns = $lens->getTable()->getColumns();
        $query = $lens->query();
        $model = $query->getModel();
        $relations = collect($columns)->keys()->filter(fn (string $name): bool => str_contains($name, '.'))
            ->map(fn (string $name): string => Str::beforeLast($name, '.'))->unique()->values()->all();

        $records = $this->listRecords(
            $query->with($relations),
            $request,
            searchable: collect($columns)->filter(fn (Column $column): bool => $column->isSearchable())
                ->map(fn (Column $column): string|\Closure => $this->searchOf($column->getName()))->values()->all(),
            sortable: collect($columns)->filter(fn (Column $column): bool => $column->isSortable() && ! str_contains($column->getName(), '.'))
                ->mapWithKeys(fn (Column $column): array => [$column->getName() => $column->getName()])->all(),
        );

        $canUpdate = Gate::allows('update', $model->newInstance());

        return response()->json([
            'data' => $records->getCollection()->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'path' => $record instanceof Item ? "/items/{$record->getKey()}" : null,
                'cells' => collect($columns)->map(fn (Column $column): array => $this->cellOf($column, $record))->all(),
                'can' => ['update' => $canUpdate],
            ])->values(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
            'lens' => [
                'key' => $class::uriKey(),
                'label' => $class::label(),
                'explanation' => $class::explanation(),
                'guidance' => $class::guidance(),
            ],
            'columns' => collect($columns)->map(fn (Column $column): array => $this->definitionOf($column))->values(),
            'actions' => $canUpdate ? array_map(fn (Action $action): string => $action->getName(), $lens->recordActions()) : [],
            'bulk_actions' => $canUpdate ? array_map(fn (Action $action): string => $action->getName(), $lens->toolbarActions()) : [],
            'metrics' => $this->metricsOf($lens),
            'can' => ['update' => $canUpdate],
        ]);
    }

    /**
     * The lens class of a uriKey, found among the lenses of app/Filament/Lenses.
     *
     * @return class-string<Lens>|null
     */
    private function lensOf(string $key): ?string
    {
        foreach (File::files(app_path('Filament/Lenses')) as $file) {
            $class = 'App\\Filament\\Lenses\\'.$file->getFilenameWithoutExtension();

            if (class_exists($class) && is_subclass_of($class, Lens::class) && ! (new \ReflectionClass($class))->isAbstract() && $class::uriKey() === $key) {
                return $class;
            }
        }

        return null;
    }

    /**
     * How a searchable column is searched: its own column, or the column of its relation (supplier.name_ar).
     */
    private function searchOf(string $name): string|\Closure
    {
        if (! str_contains($name, '.')) {
            return $name;
        }

        $relation = Str::beforeLast($name, '.');
        $column = Str::afterLast($name, '.');

        return fn (Builder $query, string $search): Builder => $query->orWhereHas($relation, fn (Builder $query): Builder => $query->whereLike($column, "%{$search}%", caseSensitive: false));
    }

    /**
     * What the browser needs to draw a column: its kind, its label, and whether it sorts and searches.
     *
     * @return array{name: string, label: string, type: string, sortable: bool, searchable: bool, numeric: bool, badge: bool}
     */
    private function definitionOf(Column $column): array
    {
        return [
            'name' => $column->getName(),
            'label' => (string) $column->getLabel(),
            'type' => match (true) {
                $column instanceof ImageColumn => 'image',
                $column instanceof IconColumn => 'boolean',
                default => 'text',
            },
            'sortable' => $column->isSortable() && ! str_contains($column->getName(), '.'),
            'searchable' => $column->isSearchable(),
            'numeric' => $column instanceof TextColumn && $column->isNumeric(),
            'badge' => $column instanceof TextColumn && $column->isBadge(),
        ];
    }

    /**
     * One cell, worked out by the lens's own column for the record: its value (the image's address for an image) and its color.
     *
     * @return array{value: mixed, color: ?string}
     */
    private function cellOf(Column $column, Model $record): array
    {
        $column->record($record);
        $state = $column->getState();

        if ($column instanceof ImageColumn) {
            return ['value' => is_string($state) && $state !== '' ? Storage::disk('public')->url($state) : null, 'color' => null];
        }

        $color = null;

        if ($column instanceof TextColumn) {
            try {
                $resolved = $column->getColor($state);
                $color = is_string($resolved) ? $resolved : null;
            } catch (Throwable) {
                $color = null;
            }
        }

        return ['value' => $state instanceof \BackedEnum ? $state->value : $state, 'color' => $color];
    }

    /**
     * The cards above the lens table, as the lens gives them, for the ones the user may see.
     *
     * @return list<array<string, mixed>>
     */
    private function metricsOf(Lens $lens): array
    {
        /** @var array<int, mixed> $widgets */
        $widgets = (fn (): array => $this->getHeaderWidgets())->call($lens);

        return collect($widgets)
            ->filter(fn (mixed $widget): bool => is_string($widget) && is_subclass_of($widget, ValueMetric::class) && $widget::canView())
            ->map(function (string $widget): array {
                $card = app($widget);
                $card->mount();

                return ['key' => Str::snake(Str::beforeLast(class_basename($widget), 'Metric')), ...$card->toPayload()];
            })
            ->values()
            ->all();
    }
}
