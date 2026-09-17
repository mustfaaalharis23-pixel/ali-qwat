<?php

namespace App\Metrics;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A span of time with an optional start and end. A period without a start or an end is open on that side.
 */
readonly class Period
{
    public function __construct(
        public ?CarbonImmutable $start,
        public ?CarbonImmutable $end,
    ) {}

    /**
     * Keeps only the rows whose column is inside this period, including its first and last moment.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function applyTo(Builder $query, string $column): Builder
    {
        return $query
            ->when($this->start, fn (Builder $query): Builder => $query->where($column, '>=', $this->start))
            ->when($this->end, fn (Builder $query): Builder => $query->where($column, '<=', $this->end));
    }
}
