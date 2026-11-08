<?php

namespace App\Http\Controllers\Admin\System;

use App\Enums\InvoiceType;
use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\System\EnumValue;
use App\Http\Resources\Admin\System\GeneralDescriptionResource;
use App\Models\GeneralDescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The general descriptions: a name, a type (input or output), and whether money under it is a capital movement.
 * The purchases description is never deleted from the screens, and never becomes a capital movement.
 */
class GeneralDescriptionController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', GeneralDescription::class);

        $descriptions = $this->listRecords(
            GeneralDescription::query(),
            $request,
            searchable: ['id', 'name'],
            sortable: ['id' => 'id', 'name' => 'name'],
            filters: ['type' => fn (Builder $query, string $value) => $query->where('type', $value)],
            defaultSort: ['column' => 'id', 'direction' => 'asc'],
        );

        return GeneralDescriptionResource::collection($descriptions)->additional([
            'can' => ['create' => Gate::allows('create', GeneralDescription::class)],
            'types' => EnumValue::options(InvoiceType::class),
        ]);
    }

    public function show(int $description): GeneralDescriptionResource
    {
        $record = $this->find($description);
        Gate::authorize('view', $record);

        return new GeneralDescriptionResource($record);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', GeneralDescription::class);

        $description = GeneralDescription::query()->create($this->validated($request));

        return (new GeneralDescriptionResource($description))->response()->setStatusCode(201);
    }

    public function update(Request $request, int $description): GeneralDescriptionResource
    {
        $record = $this->find($description);
        Gate::authorize('update', $record);

        $data = $this->validated($request);

        if ($record->is_purchases_description) {
            unset($data['is_capital_movement']);
        }

        $record->update($data);

        return new GeneralDescriptionResource($this->find($record->id));
    }

    public function destroy(int $description): JsonResponse
    {
        $record = $this->find($description);
        abort_if($record->is_purchases_description, 403);
        Gate::authorize('delete', $record);

        $record->delete();

        return response()->json(status: 204);
    }

    public function restore(int $description): GeneralDescriptionResource
    {
        $record = GeneralDescription::onlyTrashed()->findOrFail($description);
        Gate::authorize('restore', $record);

        $record->restore();

        return new GeneralDescriptionResource($this->find($record->id));
    }

    public function forceDelete(int $description): JsonResponse
    {
        $record = $this->find($description);
        abort_if($record->is_purchases_description, 403);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    private function find(int $id): GeneralDescription
    {
        return GeneralDescription::withTrashed()->withCount('specificDescriptions')->findOrFail($id);
    }

    /**
     * @return array{name: string, type: string, is_capital_movement?: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InvoiceType::class)],
            'is_capital_movement' => ['sometimes', 'boolean'],
        ], attributes: [
            'name' => __('filament/resources/general_description.fields.name'),
            'type' => __('filament/resources/general_description.fields.type'),
            'is_capital_movement' => __('filament/resources/general_description.fields.is_capital_movement'),
        ]);

        if (array_key_exists('is_capital_movement', $data)) {
            $data['is_capital_movement'] = (bool) $data['is_capital_movement'];
        }

        return $data;
    }
}
