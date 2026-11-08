<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Purchases\InvoiceCategoryResource;
use App\Models\InvoiceCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The categories of the purchase invoices: a name, unique among the categories. A category that has invoices cannot be deleted
 * (the model refuses it, and the list says so with is_used). A new category can also be added from the purchase invoice form.
 */
class InvoiceCategoryController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', InvoiceCategory::class);

        $categories = $this->listRecords(
            InvoiceCategory::query()
                ->withCount('invoices')
                ->withExists(['invoices as is_used' => fn (Builder $query): Builder => $query->withTrashed()]),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name', 'invoices_count' => 'invoices_count'],
            defaultSort: ['column' => 'id', 'direction' => 'desc'],
        );

        return InvoiceCategoryResource::collection($categories)->additional([
            'can' => ['create' => Gate::allows('create', InvoiceCategory::class)],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', InvoiceCategory::class);

        return (new InvoiceCategoryResource(InvoiceCategory::query()->create($this->validated($request))))->response()->setStatusCode(201);
    }

    public function update(Request $request, InvoiceCategory $category): InvoiceCategoryResource
    {
        Gate::authorize('update', $category);

        $category->update($this->validated($request, $category));

        return new InvoiceCategoryResource($category);
    }

    public function destroy(InvoiceCategory $category): JsonResponse
    {
        Gate::authorize('delete', $category);

        $category->delete();

        return response()->json(status: 204);
    }

    public function restore(int $category): InvoiceCategoryResource
    {
        $record = InvoiceCategory::onlyTrashed()->findOrFail($category);
        Gate::authorize('restore', $record);

        $record->restore();

        return new InvoiceCategoryResource($record);
    }

    public function forceDelete(int $category): JsonResponse
    {
        $record = InvoiceCategory::withTrashed()->findOrFail($category);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?InvoiceCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(InvoiceCategory::class, 'name')->ignore($category)],
        ], attributes: ['name' => __('filament/resources/invoice_category.fields.name')]);
    }
}
