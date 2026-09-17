<?php

namespace App\Filament\Widgets\Metrics;

use App\Enums\OrderPaymentType;
use App\Metrics\Calculation;
use App\Metrics\Calculations\ClosureCalculation;
use App\Metrics\Currency;
use App\Metrics\MetricRange;
use App\Metrics\Period;
use App\Models\Order;

/**
 * The sales box of one payment type in Iraqi dinar: its sales from the orders minus all the money transferred from it, of every cashier.
 * It is the money still in the box now, so it has only one range, all. The formula comes from Order::boxBalance().
 * Each box card only gives its payment type; the label and the info are read by the payment type.
 */
abstract class SalesBoxMetric extends ValueMetric
{
    /**
     * The payment type whose box this card shows.
     */
    abstract protected function paymentType(): OrderPaymentType;

    protected function label(): string
    {
        return __("metrics.cards.{$this->paymentType()->value}_sales_box");
    }

    protected function explanation(): string
    {
        return __("metrics.cards_info.{$this->paymentType()->value}_sales_box.explanation");
    }

    protected function example(): string
    {
        return __("metrics.cards_info.{$this->paymentType()->value}_sales_box.example");
    }

    protected function calculation(): Calculation
    {
        return new ClosureCalculation(fn (Period $period): float => Order::boxBalance($this->paymentType(), $period));
    }

    protected function ranges(): array
    {
        return [MetricRange::All];
    }

    protected function defaultRange(): MetricRange
    {
        return MetricRange::All;
    }

    protected function currency(): ?Currency
    {
        return Currency::IraqiDinar;
    }
}
