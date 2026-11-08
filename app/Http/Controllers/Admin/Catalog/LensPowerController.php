<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\Catalog\LensPowerResource;
use App\Models\LensPower;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The lens powers, from the lowest to the highest: a number of up to two decimals, unique among the powers.
 * A power on purchase lines cannot be deleted (the model refuses it, and the list says so with is_used).
 */
class LensPowerController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', LensPower::class);

        $powers = $this->listRecords(
            LensPower::query()
                ->withCount('invoiceItemGroups')
                ->withExists(['invoiceItemGroups as is_used' => fn (Builder $query): Builder => $query->withTrashed()]),
            $request,
            searchable: [fn (Builder $query, string $search): Builder => $query->orWhereRaw('CAST(value AS TEXT) LIKE ?', ["%{$search}%"])],
            sortable: ['id' => 'id', 'value' => 'value', 'invoice_item_groups_count' => 'invoice_item_groups_count'],
            defaultSort: ['column' => 'value', 'direction' => 'asc'],
        );

        return LensPowerResource::collection($powers)->additional([
            'can' => ['create' => Gate::allows('create', LensPower::class)],
        ]);
    }

    public function store(Request $request): LensPowerResource
    {
        Gate::authorize('create', LensPower::class);

        return new LensPowerResource(LensPower::query()->create($this->validated($request)));
    }

    public function update(Request $request, LensPower $lensPower): LensPowerResource
    {
        Gate::authorize('update', $lensPower);

        $lensPower->update($this->validated($request, $lensPower));

        return new LensPowerResource($lensPower);
    }

    public function destroy(LensPower $lensPower): JsonResponse
    {
        Gate::authorize('delete', $lensPower);

        $lensPower->delete();

        return response()->json(status: 204);
    }

    public function restore(int $lensPower): LensPowerResource
    {
        $record = LensPower::onlyTrashed()->findOrFail($lensPower);
        Gate::authorize('restore', $record);

        $record->restore();

        return new LensPowerResource($record);
    }

    public function forceDelete(int $lensPower): JsonResponse
    {
        $record = LensPower::withTrashed()->findOrFail($lensPower);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * @return array{value: string}
     */
    private function validated(Request $request, ?LensPower $lensPower = null): array
    {
        return $request->validate([
            'value' => ['required', 'numeric', 'decimal:0,2', Rule::unique(LensPower::class, 'value')->ignore($lensPower)],
        ], attributes: ['value' => __('filament/resources/lens_power.fields.value')]);
    }
}
