<?php

namespace App\Http\Controllers\Admin\Concerns;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * How every list of the admin API reads its request, the same way the DataTable of the browser sends it:
 * ?search= (on the given columns), ?sort=&direction= (only on the given columns), ?filter[key]= (each filter a closure),
 * ?trashed=with|only for the models that soft delete, and ?page=&per_page= (at most 100).
 */
trait ListsRecords
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string|Closure(Builder<TModel>, string): void>  $searchable  columns, or closures that add an orWhere for the search
     * @param  array<string, string|Closure(Builder<TModel>, string): void>  $sortable  the sort key the browser sends => its column, or a closure that orders by it
     * @param  array<string, Closure(Builder<TModel>, string): void>  $filters  the filter key => what it does with the value
     * @param  array{column: string, direction: string}|null  $defaultSort
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function listRecords(Builder $query, Request $request, array $searchable = [], array $sortable = [], array $filters = [], ?array $defaultSort = null): LengthAwarePaginator
    {
        $search = trim((string) $request->query('search', ''));

        if ($search !== '' && $searchable !== []) {
            $query->where(function (Builder $query) use ($searchable, $search): void {
                foreach ($searchable as $column) {
                    if ($column instanceof Closure) {
                        $column($query, $search);
                    } elseif ($column === 'id') {
                        if (ctype_digit($search)) {
                            $query->orWhere($query->getModel()->qualifyColumn('id'), (int) $search);
                        }
                    } else {
                        $query->orWhereLike($column, "%{$search}%", caseSensitive: false);
                    }
                }
            });
        }

        foreach ((array) $request->query('filter', []) as $key => $value) {
            if (isset($filters[$key]) && $value !== null && $value !== '') {
                $filters[$key]($query, (string) $value);
            }
        }

        if (in_array(SoftDeletes::class, class_uses_recursive($query->getModel()), true)) {
            match ($request->query('trashed')) {
                'with' => $query->withTrashed(),
                'only' => $query->onlyTrashed(),
                default => null,
            };
        }

        $sort = (string) $request->query('sort', '');
        $direction = $request->query('direction') === 'desc' ? 'desc' : 'asc';

        if (isset($sortable[$sort])) {
            $sortable[$sort] instanceof Closure ? $sortable[$sort]($query, $direction) : $query->orderBy($sortable[$sort], $direction);
        } elseif ($defaultSort !== null) {
            $query->orderBy($defaultSort['column'], $defaultSort['direction']);
        }

        $query->orderBy($query->getModel()->getQualifiedKeyName(), $direction === 'desc' || ($defaultSort['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc');

        return $query->paginate(min(100, max(1, $request->integer('per_page') ?: 25)))->withQueryString();
    }

    /**
     * What the signed-in user may do with one record, for the buttons of its row and its page.
     *
     * @return array{view: bool, update: bool, delete: bool, restore: bool, force_delete: bool}
     */
    public static function abilitiesOf(Model $record): array
    {
        $trashed = method_exists($record, 'trashed') && $record->trashed();

        return [
            'view' => Gate::allows('view', $record),
            'update' => ! $trashed && Gate::allows('update', $record),
            'delete' => ! $trashed && Gate::allows('delete', $record),
            'restore' => $trashed && Gate::allows('restore', $record),
            'force_delete' => $trashed && Gate::allows('forceDelete', $record),
        ];
    }
}
