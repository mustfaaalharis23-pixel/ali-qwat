<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Lenses\Lens;
use App\Lenses\LensColumn;
use App\Metrics\Cards\ValueMetric;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Every lens (app/Lenses) through one endpoint: /admin-api/lenses/{uriKey}.
 * The lens itself decides everything: who may open it (canAccess), its query, its columns, its actions, and its cards.
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
        $columns = $lens->columns();
        $query = $lens->query();
        $model = $query->getModel();
        $relations = collect($columns)->filter(fn (LensColumn $column): bool => $column->isOfARelation())
            ->map(fn (LensColumn $column): string => Str::beforeLast($column->name, '.'))->unique()->values()->all();

        $records = $this->listRecords(
            $query->with($relations),
            $request,
            searchable: collect($columns)->filter(fn (LensColumn $column): bool => $column->isSearchable())
                ->map(fn (LensColumn $column): string|\Closure => $this->searchOf($column->name))->values()->all(),
            sortable: collect($columns)->filter(fn (LensColumn $column): bool => $column->isSortable())
                ->mapWithKeys(fn (LensColumn $column): array => [$column->name => $column->name])->all(),
        );

        $canUpdate = Gate::allows('update', $model->newInstance());

        return response()->json([
            'data' => $records->getCollection()->map(fn (Model $record): array => [
                'id' => $record->getKey(),
                'path' => $record instanceof Item ? "/items/{$record->getKey()}" : null,
                'cells' => collect($columns)->mapWithKeys(fn (LensColumn $column): array => [$column->name => $column->cell($record)])->all(),
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
            'columns' => array_map(fn (LensColumn $column): array => $column->definition(), $columns),
            'actions' => $canUpdate ? $lens->recordActions() : [],
            'bulk_actions' => $canUpdate ? $lens->bulkActions() : [],
            'metrics' => $this->metricsOf($lens),
            'can' => ['update' => $canUpdate],
        ]);
    }

    /**
     * The lens class of a uriKey, found among the lenses of app/Lenses.
     *
     * @return class-string<Lens>|null
     */
    private function lensOf(string $key): ?string
    {
        foreach (File::files(app_path('Lenses')) as $file) {
            $class = 'App\\Lenses\\'.$file->getFilenameWithoutExtension();

            if (class_exists($class) && is_subclass_of($class, Lens::class) && ! (new ReflectionClass($class))->isAbstract() && $class::uriKey() === $key) {
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
     * The cards above the lens table, as the lens gives them, for the ones the user may see.
     *
     * @return list<array<string, mixed>>
     */
    private function metricsOf(Lens $lens): array
    {
        return collect($lens->metrics())
            ->filter(fn (string $card): bool => is_subclass_of($card, ValueMetric::class) && $card::canView())
            ->map(fn (string $card): array => ['key' => Str::snake(Str::beforeLast(class_basename($card), 'Metric')), ...app($card)->toPayload()])
            ->values()
            ->all();
    }
}
