<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Catalog\ItemCategoryResource;
use App\Models\ItemCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The categories of the items: a name, unique among the categories. A category that has items cannot be deleted
 * (the model refuses it, and the list says so with is_used).
 */
class ItemCategoryController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', ItemCategory::class);

        $categories = $this->listRecords(
            ItemCategory::query()
                ->withCount('items')
                ->withExists(['items as is_used' => fn (Builder $query): Builder => $query->withTrashed()]),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name', 'items_count' => 'items_count'],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return ItemCategoryResource::collection($categories)->additional([
            'can' => ['create' => Gate::allows('create', ItemCategory::class)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', ItemCategory::class);

        return (new ItemCategoryResource(ItemCategory::query()->create($this->validated($request))))->response()->setStatusCode(201);
    }

    public function update(Request $request, ItemCategory $category): ItemCategoryResource
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request, $category));

        return new ItemCategoryResource($category);
    }

    public function destroy(ItemCategory $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->json(status: 204);
    }

    public function restore(int $category): ItemCategoryResource
    {
        $record = ItemCategory::onlyTrashed()->findOrFail($category);
        Gate::authorize('restore', $record);

        $record->restore();

        return new ItemCategoryResource($record);
    }

    public function forceDelete(int $category): JsonResponse
    {
        $record = ItemCategory::withTrashed()->findOrFail($category);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?ItemCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(ItemCategory::class, 'name')->ignore($category)],
        ], attributes: ['name' => __('filament/resources/item_category.fields.name')]);
    }
}
