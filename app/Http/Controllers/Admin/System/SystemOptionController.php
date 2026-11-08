<?php

namespace App\Http\Controllers\Admin\System;

use App\Http\Controllers\Controller;
use App\Models\GeneralDescription;
use App\Models\SpecificDescription;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The narrowed choices of the accounting forms, in the shape of /options: { data: [{ value, label, description }] }.
 * The general descriptions of one type, never the purchases description, each saying whether it holds transfer descriptions;
 * and the specific descriptions under one general description. Only names are shown, like the selects of the panel's forms.
 */
class SystemOptionController extends Controller
{
    public function generalDescriptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $descriptions = GeneralDescription::query()
            ->where('is_purchases_description', false)
            ->when($request->filled('type'), fn (Builder $query) => $query->where('type', (string) $request->query('type')))
            ->when($request->filled('ids'), fn (Builder $query) => $query->withTrashed()->whereKey((array) $request->query('ids')))
            ->when($search !== '' && ! $request->filled('ids'), fn (Builder $query) => $query->whereLike('name', "%{$search}%", caseSensitive: false))
            ->withExists(['specificDescriptions as holds_transfer' => fn (Builder $query) => $query->whereNotNull('transfer_type')])
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $descriptions->map(fn (GeneralDescription $description): array => [
                'value' => $description->id,
                'label' => $description->name,
                'description' => null,
                'type' => $description->type->value,
                'holds_transfer' => (bool) $description->holds_transfer,
            ])->values(),
        ]);
    }

    public function specificDescriptions(Request $request): JsonResponse
    {
        $search = trim((string) $request->query('search', ''));

        $descriptions = SpecificDescription::query()
            ->where('general_description_id', $request->integer('general_description_id'))
            ->when($request->filled('ids'), fn (Builder $query) => $query->withTrashed()->whereKey((array) $request->query('ids')))
            ->when($search !== '' && ! $request->filled('ids'), fn (Builder $query) => $query->whereLike('name', "%{$search}%", caseSensitive: false))
            ->orderBy('name')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $descriptions->map(fn (SpecificDescription $description): array => [
                'value' => $description->id,
                'label' => $description->name,
                'description' => null,
            ])->values(),
        ]);
    }
}
