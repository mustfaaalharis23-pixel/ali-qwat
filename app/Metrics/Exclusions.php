<?php

namespace App\Metrics;

/**
 * The descriptions the reader of a report took out of it, to see the picture without them:
 * revenues by their specific description, and expenses by their general description.
 *
 * Nothing is hidden by an exclusion. The report still shows every row, marked as left out, and only the totals
 * and what is worked out from them skip it. An exclusion is a way of asking a question, not a way of editing:
 * as long as one is active, the report does not say whether it matches, because the number it would check
 * is no longer the whole month.
 */
readonly class Exclusions
{
    /**
     * @param  list<int>  $revenueDescriptionIds  the specific descriptions of the revenues that are left out
     * @param  list<int>  $expenseDescriptionIds  the general descriptions of the expenses that are left out
     */
    public function __construct(
        public array $revenueDescriptionIds = [],
        public array $expenseDescriptionIds = [],
    ) {}

    /**
     * Whether a revenue under that specific description is left out.
     */
    public function excludesRevenue(int $specificDescriptionId): bool
    {
        return in_array($specificDescriptionId, $this->revenueDescriptionIds, true);
    }

    /**
     * Whether the expenses under that general description are left out.
     */
    public function excludesExpense(int $generalDescriptionId): bool
    {
        return in_array($generalDescriptionId, $this->expenseDescriptionIds, true);
    }

    /**
     * Whether anything at all is left out.
     */
    public function any(): bool
    {
        return $this->revenueDescriptionIds !== [] || $this->expenseDescriptionIds !== [];
    }
}
