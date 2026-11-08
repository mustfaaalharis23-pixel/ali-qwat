<?php

namespace App\Http\Controllers\Admin\Purchases;

use App\Http\Requests\Admin\Purchases\PurchaseInvoiceRequest;
use App\Invoices\ConfirmPurchaseInvoice;
use App\Models\GeneralDescription;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemColor;
use App\Models\InvoiceItemGroup;
use App\Models\Payoff;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saves a purchase invoice the way the panel's create and edit pages save it: the invoice first, then its lines, and the groups
 * and colors of each line, kept, changed, added, or removed like the form's repeaters do. The models apply every business rule
 * (names, stages, costs, batches, amounts); a rule a line breaks is reported on that line.
 * A new invoice is confirmed for its importer, its creator, and its initial payment becomes its first payoff.
 * All of it is saved together or not at all.
 */
class PurchaseInvoiceWriter
{
    public function create(PurchaseInvoiceRequest $request): Invoice
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $data): Invoice {
            $purchases = GeneralDescription::purchases();

            $invoice = Invoice::query()->create([
                'general_description_id' => $purchases->id,
                'type' => $purchases->type,
                'user_id' => $request->user()->id,
                'supplier_id' => $data['supplier_id'],
                'invoice_category_id' => $data['invoice_category_id'],
                'date' => $data['date'],
                'dinar_rate' => $data['dinar_rate'],
                'cbm_price' => $data['cbm_price'],
                'images' => array_values($data['images'] ?? []),
                'notes' => $data['notes'] ?? null,
                ...$request->exchangeRateForCreation(),
            ]);

            $this->saveLines($request, $invoice, $data['items']);
            $invoice->refresh()->recalculateAmounts();

            (new ConfirmPurchaseInvoice)->handleOnCreation($invoice);

            $initialPayment = (float) ($data['initial_payment'] ?? 0);

            if ($initialPayment > 0) {
                Payoff::query()->create([
                    'invoice_id' => $invoice->getKey(),
                    'amount' => $initialPayment,
                    'date' => $invoice->date,
                    'is_initial_payment' => true,
                ]);
            }

            return $invoice->refresh();
        });
    }

    public function update(PurchaseInvoiceRequest $request, Invoice $invoice): Invoice
    {
        $data = $request->validated();

        return DB::transaction(function () use ($request, $invoice, $data): Invoice {
            $invoice->fill([
                'supplier_id' => $data['supplier_id'],
                'invoice_category_id' => $data['invoice_category_id'],
                'date' => $data['date'],
                'dinar_rate' => $data['dinar_rate'],
                'images' => array_values($data['images'] ?? []),
                'notes' => $data['notes'] ?? null,
            ]);

            if ($request->canChangeExchangeRate() && array_key_exists('exchange_rate', $data)) {
                $invoice->exchange_rate = $data['exchange_rate'];
            }

            if (! $request->isCbmPriceLocked()) {
                $invoice->cbm_price = $data['cbm_price'];
            }

            $invoice->save();

            $this->saveLines($request, $invoice, $data['items']);
            $invoice->refresh()->recalculateAmounts();

            return $invoice->refresh();
        });
    }

    /**
     * The lines that are no longer sent are removed first, then every sent line is saved with its unit cost as typed and its
     * total price (its pieces × the unit cost), and then its groups and colors.
     *
     * @param  array<int, array<string, mixed>>  $lines
     */
    private function saveLines(PurchaseInvoiceRequest $request, Invoice $invoice, array $lines): void
    {
        $existing = $invoice->items()->get()->keyBy('id');
        $kept = collect($lines)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();

        $existing->except($kept)->each(fn (InvoiceItem $line) => $this->onLine('items', fn () => $line->delete()));

        foreach (array_values($lines) as $index => $data) {
            $this->onLine("items.{$index}", function () use ($request, $invoice, $existing, $data): void {
                $line = $existing->get((int) ($data['id'] ?? 0)) ?? new InvoiceItem(['invoice_id' => $invoice->id]);
                $packets = $request->packetsOf($data);
                $piecesPerPacket = $request->piecesPerPacketOf($data);

                $line->fill([
                    'specific_description_id' => $data['specific_description_id'],
                    'boxes_count' => $packets,
                    'pieces_per_packet' => $piecesPerPacket,
                    'boxes_per_carton' => $data['boxes_per_carton'],
                    'carton_cbm' => $data['carton_cbm'],
                    'unit_cost' => (float) $data['unit_cost'],
                    'price' => $request->lineTotal($data),
                ])->save();

                $this->syncChildren($line->groups(), $data['groups'] ?? [], fn (array $group): array => [
                    'lens_power_id' => $group['lens_power_id'] ?? null,
                    'boxes_count' => $group['boxes_count'],
                ]);

                $this->syncChildren($line->colors(), $data['colors'] ?? [], fn (array $color): array => [
                    'color_id' => $color['color_id'],
                    'pieces' => $color['pieces'],
                ]);
            });
        }
    }

    /**
     * Keeps, changes, adds, and removes the groups or the colors of a line to match the sent rows.
     *
     * @param  HasMany<InvoiceItemGroup|InvoiceItemColor, InvoiceItem>  $relation
     * @param  array<int, array<string, mixed>>  $rows
     * @param  callable(array<string, mixed>): array<string, mixed>  $attributes
     */
    private function syncChildren(HasMany $relation, array $rows, callable $attributes): void
    {
        $existing = $relation->get()->keyBy('id');
        $kept = collect($rows)->pluck('id')->filter()->map(fn (mixed $id): int => (int) $id)->all();

        $existing->except($kept)->each(fn (Model $child) => $child->delete());

        foreach ($rows as $row) {
            $child = $existing->get((int) ($row['id'] ?? 0));

            $child === null ? $relation->create($attributes($row)) : $child->fill($attributes($row))->save();
        }
    }

    /**
     * Runs a save of a line, and reports a model rule it breaks on that line, such as "items.0.price".
     */
    private function onLine(string $path, callable $save): void
    {
        try {
            $save();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key): array => ["{$path}.{$key}" => $messages])
                ->all());
        }
    }
}
