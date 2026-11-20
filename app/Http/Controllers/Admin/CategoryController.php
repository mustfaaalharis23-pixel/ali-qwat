<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\CategoryResource;
use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Both category routes use the same records and keep their existing section permissions. */
abstract class CategoryController extends Controller
{
    use ListsRecords;

    /** @var class-string<Category> */
    protected const string MODEL = Category::class;

    public function index(Request $request): AnonymousResourceCollection
    {
        $model = static::MODEL;
        Gate::authorize('viewAny', $model);

        $categories = $this->listRecords(
            $model::query()
                ->withCount(['items', 'invoices'])
                ->withExists([
                    'items as has_linked_items' => fn (Builder $query): Builder => $query->withTrashed(),
                    'invoices as has_linked_invoices' => fn (Builder $query): Builder => $query->withTrashed(),
                ]),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name', 'items_count' => 'items_count', 'invoices_count' => 'invoices_count'],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return CategoryResource::collection($categories)->additional([
            'can' => ['create' => Gate::allows('create', $model)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $model = static::MODEL;
        Gate::authorize('create', $model);

        return (new CategoryResource($model::query()->create($this->validated($request))))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $category): CategoryResource
    {
        $model = static::MODEL;
        $record = $model::query()->findOrFail($category);
        Gate::authorize('update', $record);
        $record->update($this->validated($request, $record));

        return new CategoryResource($record);
    }

    public function destroy(int $category): JsonResponse
    {
        DB::transaction(function () use ($category): void {
            $model = static::MODEL;
            $record = $model::query()->lockForUpdate()->findOrFail($category);
            Gate::authorize('delete', $record);
            $record->delete();
        });

        return response()->json(status: 204);
    }

    public function restore(int $category): CategoryResource
    {
        $model = static::MODEL;
        $record = $model::onlyTrashed()->findOrFail($category);
        Gate::authorize('restore', $record);
        $record->restore();

        return new CategoryResource($record);
    }

    public function forceDelete(int $category): JsonResponse
    {
        DB::transaction(function () use ($category): void {
            $model = static::MODEL;
            $record = $model::withTrashed()->lockForUpdate()->findOrFail($category);
            Gate::authorize('forceDelete', $record);
            $record->forceDelete();
        });

        return response()->json(status: 204);
    }

    /** @return array{name: string} */
    private function validated(Request $request, ?Category $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(static::MODEL, 'name')->ignore($category)],
        ], attributes: ['name' => __('filament/resources/category.fields.name')]);
    }
}
