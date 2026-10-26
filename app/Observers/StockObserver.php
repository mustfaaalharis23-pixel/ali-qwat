<?php

namespace App\Observers;

use App\Models\Batch;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceItemGroup;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place that knows every case where an item's stock changes.
 * The available quantity and the incoming quantity are two separate numbers: the incoming quantity is what the importer
 * confirmed and the receiver did not, it is information only, and it never takes part in the available quantity or in selling. It never adds or takes anything:
 * it calls Item::recalculateStock(), which reads the numbers again from the beginning, so the order of the events
 * and any event missed before do not matter.
 *
 * The cases, all of them:
 *   purchase line saved, deleted, restored, or force deleted — its batches are the stock coming in
 *   a line moved to another item — the item it left and the item it went to
 *   a group of a line saved, deleted, or restored — the line's batches follow its groups
 *   invoice saved (confirmed by a side, or a confirmation taken back), deleted, restored, or force deleted
 *   sold line saved, deleted, restored, or force deleted, and a sold line moved to another item
 *   order deleted, restored, or force deleted — everything sold on it comes back or goes out again
 */
class StockObserver
{
    public function saved(Model $model): void
    {
        $this->recalculateFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->recalculateFor($model);
    }

    public function restored(Model $model): void
    {
        $this->recalculateFor($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->recalculateFor($model);
    }

    /**
     * Recalculates every item this change touches. A change on the orders only moves the available quantity,
     * so it writes that number alone; a change on the purchase side can move the available quantity,
     * the incoming quantity, and the cost, so it writes the three of them.
     */
    protected function recalculateFor(Model $model): void
    {
        $isSale = $model instanceof Order || $model instanceof OrderItem;

        Item::query()
            ->whereKey($this->itemIdsOf($model))
            ->get()
            ->each(fn (Item $item): mixed => $isSale ? $item->recalculateAvailableQuantity() : $item->recalculateStock());
    }

    /**
     * The items a change touches: the item of the line, the item it was moved from,
     * and, for an invoice or an order, the items of everything on it.
     *
     * @return list<int>
     */
    protected function itemIdsOf(Model $model): array
    {
        $ids = match (true) {
            $model instanceof InvoiceItem, $model instanceof OrderItem => [$model->item_id, $model->getOriginal('item_id')],
            $model instanceof InvoiceItemGroup => [InvoiceItem::withTrashed()->find($model->invoice_item_id)?->item_id],
            $model instanceof Invoice => Batch::withTrashed()->where('invoice_id', $model->id)->pluck('item_id')->all(),
            $model instanceof Order => OrderItem::withTrashed()->where('order_id', $model->id)->pluck('item_id')->all(),
            default => [],
        };

        return collect($ids)->filter()->unique()->values()->all();
    }
}
