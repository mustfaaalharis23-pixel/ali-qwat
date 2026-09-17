<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CustomerIndexRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    /**
     * The customers, searched by name or by phone and sorted by name.
     */
    public function index(CustomerIndexRequest $request): AnonymousResourceCollection
    {
        $customers = Customer::query()
            ->when($request->filled('search'), fn (Builder $query): Builder => $query
                ->where(fn (Builder $query): Builder => $query
                    ->whereLike('name', '%'.$request->string('search').'%', caseSensitive: false)
                    ->orWhereLike('phone', '%'.$request->string('search').'%', caseSensitive: false)))
            ->orderBy('name')
            ->paginate($request->integer('per_page') ?: 25)
            ->withQueryString();

        return CustomerResource::collection($customers);
    }
}
