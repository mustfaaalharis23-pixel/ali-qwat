<?php

namespace App\Http\Controllers\Admin\Catalog;

use App\Http\Controllers\Admin\Concerns\ListsRecords;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\ColorResource;
use App\Models\Color;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The colors: a name, unique among the colors. A color in the packets of a purchase line cannot be deleted
 * (the model refuses it, and the list says so with is_used).
 */
class ColorController extends Controller
{
    use ListsRecords;

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Color::class);

        $colors = $this->listRecords(
            Color::query()
                ->withCount('invoiceItemColors')
                ->withExists(['invoiceItemColors as is_used' => fn (Builder $query): Builder => $query->withTrashed()]),
            $request,
            searchable: ['name'],
            sortable: ['id' => 'id', 'name' => 'name', 'invoice_item_colors_count' => 'invoice_item_colors_count'],
            defaultSort: ['column' => 'name', 'direction' => 'asc'],
        );

        return ColorResource::collection($colors)->additional([
            'can' => ['create' => Gate::allows('create', Color::class)],
        ]);
    }

    public function store(Request $request): ColorResource
    {
        Gate::authorize('create', Color::class);

        return new ColorResource(Color::query()->create($this->validated($request)));
    }

    public function update(Request $request, Color $color): ColorResource
    {
        Gate::authorize('update', $color);

        $color->update($this->validated($request, $color));

        return new ColorResource($color);
    }

    public function destroy(Color $color): JsonResponse
    {
        Gate::authorize('delete', $color);

        $color->delete();

        return response()->json(status: 204);
    }

    public function restore(int $color): ColorResource
    {
        $record = Color::onlyTrashed()->findOrFail($color);
        Gate::authorize('restore', $record);

        $record->restore();

        return new ColorResource($record);
    }

    public function forceDelete(int $color): JsonResponse
    {
        $record = Color::withTrashed()->findOrFail($color);
        Gate::authorize('forceDelete', $record);

        $record->forceDelete();

        return response()->json(status: 204);
    }

    /**
     * @return array{name: string}
     */
    private function validated(Request $request, ?Color $color = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique(Color::class, 'name')->ignore($color)],
        ], attributes: ['name' => __('filament/resources/color.fields.name')]);
    }
}
