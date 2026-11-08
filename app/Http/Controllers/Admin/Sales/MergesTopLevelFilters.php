<?php

namespace App\Http\Controllers\Admin\Sales;

use Illuminate\Http\Request;

/**
 * Lets a list take some of its filters at the top of the query (?customer_id=5&from=2026-09-01) as well as in filter[...], for the
 * filters a page keeps in its own controls (a customer search, a period) next to the table's own filters.
 */
trait MergesTopLevelFilters
{
    /**
     * @param  list<string>  $keys
     */
    protected function mergeTopLevelFilters(Request $request, array $keys): void
    {
        $topLevel = array_filter($request->only($keys), fn (mixed $value): bool => is_scalar($value) && $value !== '');

        if ($topLevel !== []) {
            $request->query->set('filter', [...(array) $request->query('filter', []), ...$topLevel]);
        }
    }
}
