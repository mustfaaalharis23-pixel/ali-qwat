<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\OrderPaymentType;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\System\EnumValue;
use App\Http\Resources\Admin\System\SpecificDescriptionResource;
use App\Models\GeneralDescription;
use App\Models\SpecificDescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The specific descriptions, the lines' descriptions under the general descriptions. The items' own descriptions are hidden
 * by default (?filter[item_descriptions]=with shows them too, =only shows only them) and are never edited or deleted here.
 * New descriptions go under any general description but the purchases one. Only a super user sets a transfer type.
 * The general description page lists all of its own descriptions, item descriptions included, with ?general_description_id=.
 */
class SpecificDescriptionController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', SpecificDescription::class);

        $itemDescriptions = (string) ($request->query('filter')['item_descriptions'] ?? '');

        $descriptions = $this->listRecords(
            SpecificDescription::query()
                ->with(['generalDescription' => fn ($query) => $query->withTrashed(), 'item' => fn ($query) => $query->withTrashed()])
                ->when(
                    $request->filled('general_description_id'),
                    fn (Builder $query) => $query->where('general_description_id', $request->integer('general_description_id')),
                    fn (Builder $query) => $query->when($itemDescriptions === '', fn (Builder $query) => $query->whereNull('item_id')),
                ),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name'],
            filters: [
                'general_description_id' => fn (Builder $query, string $value) => $query->where('general_description_id', (int) $value),
                'item_descriptions' => fn (Builder $query, string $value) => match ($value) {
                    'only' => $query->whereNotNull('item_id'),
                    'without' => $query->whereNull('item_id'),
                    default => null,
                },
            ],
            defaultSort: ['column' => 'id', 'direction' => 'asc'],
        );

        return SpecificDescriptionResource::collection($descriptions)->additional([
            'can' => ['create' => Gate::allows('create', SpecificDescription::class)],
            'transfer_types' => EnumValue::options(OrderPaymentType::class),
        ]);
    }

    public function show(int $description): SpecificDescriptionResource
    {
        $record = $this->find($description);
        Gate::authorize('view', $record);

        return new SpecificDescriptionResource($record);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', SpecificDescription::class);

        $data = $this->validated($request);

        if ($request->user()->is_super !== true) {
            unset($data['transfer_type']);
        }

        $description = SpecificDescription::query()->create($data);

        return (new SpecificDescriptionResource($this->find($description->id)))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $description): SpecificDescriptionResource
    {
        $record = $this->find($description);
        abort_if($record->item_id !== null, 403);
        Gate::authorize('update', $record);

        $data = $this->validated($request);

        if ($request->user()->is_super !== true) {
            unset($data['transfer_type']);
        }

        $record->update($data);

        return new SpecificDescriptionResource($this->find($record->id));
    }

    public function destroy(int $description): JsonResponse
    {
        $record = $this->find($description);
        abort_if($record->item_id !== null, 403);
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(status: 204);
    }

    public function restore(int $description): SpecificDescriptionResource
    {
        $record = SpecificDescription::onlyTrashed()->findOrFail($description);
        Gate::authorize('restore', $record);

        $record->restore();

        return new SpecificDescriptionResource($this->find($record->id));
    }

    public function forceDelete(int $description): JsonResponse
    {
        $record = $this->find($description);
        abort_if($record->item_id !== null, 403);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    private function find(int $id): SpecificDescription
    {
        return SpecificDescription::withTrashed()
            ->with(['generalDescription' => fn ($query) => $query->withTrashed(), 'item' => fn ($query) => $query->withTrashed()])
            ->findOrFail($id);
    }

    /**
     * @return array{general_description_id: int, name: string, transfer_type?: string|null}
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'general_description_id' => [
                'required',
                'integer',
                Rule::exists(GeneralDescription::class, 'id')->where(fn ($query) => $query->where('is_purchases_description', false))->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'transfer_type' => ['nullable', Rule::enum(OrderPaymentType::class)],
        ], attributes: [
            'general_description_id' => __('filament/resources/specific_description.fields.general_description'),
            'name' => __('filament/resources/specific_description.fields.name'),
            'transfer_type' => __('filament/resources/specific_description.fields.transfer_type'),
        ]);
    }
}
