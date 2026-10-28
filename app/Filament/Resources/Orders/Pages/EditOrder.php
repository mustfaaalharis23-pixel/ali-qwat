<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\Concerns\InteractsWithCheckoutCart;
use App\Models\Item;
use App\Models\Order;
use App\Models\OrderItem;
use App\Orders\Checkout\CheckoutData;
use App\Orders\UpdateOrder;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The edit of a saved order, on the same cart as a new sale, filled with the order's lines. Its lines count their own packets as
 * available and keep the price they were sold at; a new line takes its item's price of now. The cart starts with the order's discount. It is saved through UpdateOrder.
 */
class EditOrder extends EditRecord
{
    use InteractsWithCheckoutCart;

    protected static string $resource = OrderResource::class;

    /**
     * The page uses the full width, so the items and the cart sit side by side.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The order's saved lines, read once a request.
     *
     * @var array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>|null
     */
    protected ?array $savedLines = null;

    /**
     * A deleted order is not edited. The cart starts with the order's lines.
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        abort_if($this->getRecord()->trashed(), 404);

        $this->cart = array_map(fn (array $line): array => [
            'item_id' => $line['item_id'],
            'lens_power_id' => $line['lens_power_id'],
            'colors' => $line['colors'],
            'packets' => $line['packets'],
        ], $this->linesOfThisOrder());

        $this->cartDiscount = (float) $this->getRecord()->amount - (float) $this->getRecord()->total_amount;
    }

    /**
     * @return array<string, array{item_id: int, lens_power_id: ?int, colors: list<array{color_id: int, pieces: float}>, pieces: int, packets: int, price: float}>
     */
    protected function linesOfThisOrder(): array
    {
        /** @var Order $order */
        $order = $this->getRecord();

        return $this->savedLines ??= $order->items()->get()
            ->mapWithKeys(fn (OrderItem $line): array => [
                $line->item_id.'#'.Item::packetTypeKey($line->lens_power_id, $line->colors) => [
                    'item_id' => (int) $line->item_id,
                    'lens_power_id' => $line->lens_power_id === null ? null : (int) $line->lens_power_id,
                    'colors' => Item::colorMix($line->colors),
                    'pieces' => (int) $line->pieces,
                    'packets' => (int) round($line->packets()),
                    'price' => (float) $line->price,
                ],
            ])
            ->all();
    }

    /**
     * The order is saved through UpdateOrder, from the cart. A customer or payment type locked on the form (an order with payments)
     * keeps the order's own. An empty cart, or a rule the edit rejects, is shown as a notification and the form stays open.
     *
     * @param  Order  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($this->cart === []) {
            Notification::make()->warning()->title(__('filament/resources/order.checkout.cart_empty'))->send();

            $this->halt();
        }

        $checkoutData = new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : (int) $record->customer_id,
            paymentType: $this->paymentTypeFrom($data['payment_type'] ?? null) ?? $record->payment_type,
            date: $data['date'],
            lines: $this->checkoutLines(),
            notes: $data['notes'] ?? null,
            weeklyPayment: filled($data['weekly_payment'] ?? null) ? (float) $data['weekly_payment'] : null,
            discount: $this->cartSummary()['discount'],
        );

        try {
            return app(UpdateOrder::class)->handle($record, $checkoutData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first())
                ->send();

            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return OrderResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
