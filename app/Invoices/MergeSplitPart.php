<?php

namespace App\Invoices;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemColor;
use App\Models\InvoiceItemGroup;
use App\Models\Payoff;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Undoes a split: merges a part split off a purchase invoice back into its original.
 * Each line of the part goes back into the original line of the same item, groups (powers and colors), and packing, or becomes a new line when none matches.
 * All the part's payoffs go back to the original as they are, and the part is removed for good, which takes it out of its container.
 * The supplier's debt and the incoming stock do not change.
 */
class MergeSplitPart
{
    /**
     * @return Invoice the original, with the part merged back into it.
     *
     * @throws ValidationException
     */
    public function handle(Invoice $part): Invoice
    {
        $original = $this->ensureThePartCanBeMerged($part);

        return DB::transaction(function () use ($part, $original): Invoice {
            $originalLines = $original->items()->with(['groups', 'colors'])->get();

            foreach ($part->items()->with(['groups', 'colors'])->get() as $partLine) {
                $this->moveLineBack($partLine, $original, $originalLines->first(fn (InvoiceItem $line): bool => $this->isSameLine($line, $partLine)));
            }

            foreach ($part->payoffs()->get() as $payoff) {
                $payoff->moveToSplitInvoice($original, (float) $payoff->amount);
            }

            $part->payoffs()->onlyTrashed()->get()->each(fn (Payoff $payoff) => $payoff->forceDelete());
            $part->items()->withTrashed()->get()->each(fn (InvoiceItem $line) => $line->forceDelete());
            $part->forceDelete();

            return $original->fresh();
        });
    }

    /**
     * Adds the part's line back to the matching original line: its packets and its price, and the packets of each of its groups
     * to the original group of the same power. With no matching line, the part's line is added to the original as a new line
     * with all its details, colors, and groups.
     */
    protected function moveLineBack(InvoiceItem $partLine, Invoice $original, ?InvoiceItem $originalLine): void
    {
        if ($originalLine !== null) {
            $originalLine->update([
                'boxes_count' => $originalLine->boxes_count + $partLine->boxes_count,
                'price' => $originalLine->price + $partLine->price,
            ]);

            $originalGroups = $originalLine->groups;

            foreach ($partLine->groups as $partGroup) {
                $key = $originalGroups->search(fn (InvoiceItemGroup $group): bool => $this->groupKeyOf($group) === $this->groupKeyOf($partGroup));
                $group = $originalGroups->pull($key);
                $group->update(['boxes_count' => $group->boxes_count + $partGroup->boxes_count]);
            }

            return;
        }

        $line = $original->items()->create([
            'specific_description_id' => $partLine->specific_description_id,
            'boxes_count' => $partLine->boxes_count,
            'pieces_per_packet' => $partLine->pieces_per_packet,
            'boxes_per_carton' => $partLine->boxes_per_carton,
            'carton_cbm' => $partLine->carton_cbm,
            'produced_at' => $partLine->produced_at,
            'price' => $partLine->price,
            'unit_cost' => $partLine->unit_cost,
        ]);

        $line->colors()->createMany($partLine->colors->map(fn (InvoiceItemColor $color): array => [
            'color_id' => $color->color_id,
            'pieces' => $color->pieces,
        ])->all());

        foreach ($partLine->groups as $partGroup) {
            $line->groups()->create([
                'lens_power_id' => $partGroup->lens_power_id,
                'boxes_count' => $partGroup->boxes_count,
            ]);
        }
    }

    /**
     * Two lines are the same line when they are of the same description, colors in a packet, and powers, packed the same way,
     * at the same unit cost, and produced on the same date, as a line and the part split off it are.
     * So one item bought at two powers on two lines stays two lines.
     */
    protected function isSameLine(InvoiceItem $line, InvoiceItem $partLine): bool
    {
        return $line->specific_description_id === $partLine->specific_description_id
            && $this->colorsKeyOf($line) === $this->colorsKeyOf($partLine)
            && $this->groupKeysOf($line) === $this->groupKeysOf($partLine)
            && $this->sameNumber($line->pieces_per_packet, $partLine->pieces_per_packet)
            && $this->sameNumber($line->boxes_per_carton, $partLine->boxes_per_carton)
            && $this->sameNumber($line->carton_cbm, $partLine->carton_cbm)
            && $this->sameNumber($line->unit_cost, $partLine->unit_cost)
            && $line->produced_at?->toDateString() === $partLine->produced_at?->toDateString();
    }

    /**
     * The groups of a line as keys, sorted, to compare two lines by. The packets are not in the keys, because a part has fewer.
     *
     * @return list<string>
     */
    protected function groupKeysOf(InvoiceItem $line): array
    {
        return $line->groups->map(fn (InvoiceItemGroup $group): string => $this->groupKeyOf($group))->sort()->values()->all();
    }

    /**
     * A group as a key: its lens power, or 0 without one.
     */
    protected function groupKeyOf(InvoiceItemGroup $group): string
    {
        return (string) (int) $group->lens_power_id;
    }

    /**
     * The colors of a line as a key: the pieces of each of its colors in a packet, sorted, such as "5:10,7:14".
     */
    protected function colorsKeyOf(InvoiceItem $line): string
    {
        return $line->colors
            ->map(fn (InvoiceItemColor $color): string => (int) $color->color_id.':'.(float) $color->pieces)
            ->sort()
            ->implode(',');
    }

    /**
     * Two stored numbers are the same when both are empty, or both are set and equal.
     */
    protected function sameNumber(mixed $first, mixed $second): bool
    {
        if ($first === null || $second === null) {
            return $first === $second;
        }

        return abs((float) $first - (float) $second) < 0.0001;
    }

    /**
     * Only a part split off another purchase invoice is merged, and only when neither is deleted or confirmed
     * (their goods are not in the warehouse yet), the original is in no container (as when it was split),
     * both have the same status (so the goods keep their confirmation), and the part has no parts of its own.
     *
     * @return Invoice the original of the part.
     *
     * @throws ValidationException
     */
    protected function ensureThePartCanBeMerged(Invoice $part): Invoice
    {
        $original = $part->split_from_invoice_id === null
            ? null
            : Invoice::withTrashed()->find($part->split_from_invoice_id);

        if ($original === null || ! $part->isPurchase()) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.merge_needs_a_split_part'),
            ]);
        }

        if ($part->trashed()
            || $original->trashed()
            || $part->status === InvoiceStatus::Confirmed
            || $original->status === InvoiceStatus::Confirmed) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.merge_needs_open_invoices'),
            ]);
        }

        if ($original->container_id !== null) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.merge_original_in_a_container'),
            ]);
        }

        if ($part->status !== $original->status) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.merge_statuses_differ'),
            ]);
        }

        if ($part->splitParts()->withTrashed()->exists()) {
            throw ValidationException::withMessages([
                'invoice' => __('invoices.validation.merge_part_has_parts'),
            ]);
        }

        return $original;
    }
}
