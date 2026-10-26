<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderPaymentType;
use App\Filament\Forms\Components\DateInput;
use App\Filament\Forms\Components\MoneyInput;
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Metrics\Currency;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OrderForm
{
    /**
     * The sale: its customer, payment type, and date, then the items and the cart (built on the page, like the app's checkout),
     * with the down payment of a postpaid sale and the notes in the cart, above its confirm button, as the app has them.
     * The price of a dozen comes from the item's sale price and is not entered, because the checkout always takes
     * it from the database. An item bought with powers is sold at one of its powers, and in one of the color mixes of that power,
     * so the same item can be on two lines; an item without powers or colors is sold without them.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('filament/resources/order.sections.details'))
                    ->icon(Heroicon::OutlinedShoppingBag)
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('customer_id')
                            ->label(__('filament/resources/order.fields.customer_id'))
                            ->relationship('customer', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('payment_type')
                            ->label(__('filament/resources/order.fields.payment_type'))
                            ->options(OrderPaymentType::class)
                            ->live()
                            ->required(),
                        DateInput::make('date')
                            ->label(__('filament/resources/order.fields.date'))
                            ->defaultToday()
                            ->required(),
                    ]),
                // The items and the cart, as the app's checkout has them: the seller chooses a power and the colors of the packets
                // on an item's card and adds whole packets; the cart beside them makes the sale. Their state lives on the page.
                Section::make(__('filament/resources/order.fields.items'))
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->columnSpanFull()
                    ->schema([
                        View::make('filament.orders.checkout')
                            ->viewData(fn (CreateOrder $livewire): array => [
                                'items' => $livewire->checkoutItems(),
                                'cart' => $livewire->cartSummary(),
                            ])
                            // Rendered inside the cart, above its confirm button. The down payment and the weekly payment are set on a postpaid sale only.
                            ->schema([
                                MoneyInput::make('down_payment')
                                    ->label(__('filament/resources/order.fields.down_payment'))
                                    ->suffix(Currency::IraqiDinar->symbol())
                                    ->placeholder(__('filament/resources/order.checkout.optional'))
                                    ->minValue(0.01)
                                    ->visible(fn (Get $get): bool => self::isPostpaid($get('payment_type'))),
                                MoneyInput::make('weekly_payment')
                                    ->label(__('filament/resources/order.fields.weekly_payment'))
                                    ->suffix(Currency::IraqiDinar->symbol())
                                    ->placeholder(__('filament/resources/order.checkout.optional'))
                                    ->helperText(__('filament/resources/order.checkout.weekly_payment_hint'))
                                    ->minValue(0.01)
                                    ->visible(fn (Get $get): bool => self::isPostpaid($get('payment_type'))),
                                Textarea::make('notes')
                                    ->label(__('filament/resources/order.fields.notes'))
                                    ->placeholder(__('filament/resources/order.checkout.optional'))
                                    ->rows(2),
                            ]),
                    ]),
            ]);
    }

    /**
     * Whether the chosen payment type is postpaid. The select holds the enum once chosen, and its value before.
     */
    private static function isPostpaid(mixed $paymentType): bool
    {
        return ($paymentType instanceof OrderPaymentType ? $paymentType : OrderPaymentType::tryFrom((string) $paymentType)) === OrderPaymentType::Postpaid;
    }
}
