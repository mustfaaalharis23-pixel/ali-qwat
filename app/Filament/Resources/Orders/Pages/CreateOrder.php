<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\Concerns\InteractsWithCheckoutCart;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * The panel's sale, built the way the app's checkout is: the seller searches the items, chooses a power and the colors of its
 * packets on an item's card, and types the whole packets; the cart beside them shows each line with its pieces, its
 * dozens at the price of a dozen, and the total, and makes the sale through the same checkout as the app.
 */
class CreateOrder extends CreateRecord
{
    use InteractsWithCheckoutCart;

    protected static string $resource = OrderResource::class;

    /**
     * The page uses the full width, so the items and the cart sit side by side.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The order is saved through the checkout, from the cart, so the prices come from the items and the sale rules are applied.
     * An empty cart, or a rule the checkout rejects, is shown as a notification and the form stays open.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        if ($this->cart === []) {
            Notification::make()->warning()->title(__('filament/resources/order.checkout.cart_empty'))->send();

            $this->halt();
        }

        $checkoutData = new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            paymentType: $this->paymentTypeFrom($data['payment_type'] ?? null),
            date: $data['date'],
            lines: $this->checkoutLines(),
            notes: $data['notes'] ?? null,
            downPayment: filled($data['down_payment'] ?? null) ? (float) $data['down_payment'] : null,
            weeklyPayment: filled($data['weekly_payment'] ?? null) ? (float) $data['weekly_payment'] : null,
        );

        try {
            return app(Checkout::class)->handle(auth()->user(), $checkoutData);
        } catch (ValidationException $exception) {
            Notification::make()
                ->danger()
                ->title(collect($exception->errors())->flatten()->first())
                ->send();

            $this->halt();
        }
    }
}
