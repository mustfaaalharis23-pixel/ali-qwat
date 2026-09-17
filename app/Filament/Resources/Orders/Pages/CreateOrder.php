<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\OrderPaymentType;
use App\Filament\Resources\Orders\OrderResource;
use App\Orders\Checkout\Checkout;
use App\Orders\Checkout\CheckoutData;
use App\Orders\Checkout\CheckoutLine;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    /**
     * The page uses the full width, so the lines table has room for wide fields.
     */
    protected Width|string|null $maxContentWidth = Width::Full;

    /**
     * The order is saved through the checkout, so the prices come from the items and the sale rules are applied.
     * A rule the checkout rejects is shown as a notification and the form stays open.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $checkoutData = new CheckoutData(
            customerId: filled($data['customer_id'] ?? null) ? (int) $data['customer_id'] : null,
            paymentType: OrderPaymentType::tryFrom((string) ($data['payment_type'] ?? '')),
            date: $data['date'],
            lines: array_values(array_map(
                fn (array $line): CheckoutLine => new CheckoutLine((int) $line['item_id'], (float) $line['quantity']),
                $data['lines'] ?? [],
            )),
            notes: $data['notes'] ?? null,
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
