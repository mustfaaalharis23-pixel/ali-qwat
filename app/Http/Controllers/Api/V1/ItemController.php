<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ItemIndexRequest;
use App\Http\Resources\Api\V1\ItemResource;
use App\Models\Item;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * The items the app sells: the active ones only, searched by name. An item with nothing in stock comes too,
     * carrying what is on its way, so the seller knows it is coming and how much. What is in stock comes first.
     */
    public function index(ItemIndexRequest $request): AnonymousResourceCollection
    {
        $items = Item::query()
            ->active()
            ->when($request->filled('search'), fn (Builder $query): Builder => $query
                ->whereLike('name', '%'.$request->string('search').'%', caseSensitive: false))
            ->orderByDesc(DB::raw('case when available_quantity > 0 then 1 else 0 end'))
            ->orderBy('name')
            ->paginate($request->integer('per_page') ?: 25)
            ->withQueryString();

        return ItemResource::collection($items);
    }
}
