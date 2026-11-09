<?php

namespace App\Metrics\Cards;

use App\Metrics\Calculation;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Metrics\MetricRange;

/**
 * The template of every value metric card: a label, one number, and a list of ranges to choose from.
 * A card gives its label, its calculation, its ranges, its default range, and its info: an explanation and a simple example.
 * The info is required, so no card can be built without it. The admin application draws the card from toPayload().
 */
abstract class ValueMetric
{
    /**
     * The value of the range chosen in the browser. It is checked against the card's ranges before it is used.
     */
    public ?string $range = null;

    /**
     * Whether the signed-in user may see the card. Every card is seen unless it says otherwise.
     */
    public static function canView(): bool
    {
        return true;
    }

    /**
     * The label shown on the card.
     */
    abstract protected function label(): string;

    /**
     * The calculation of the number shown on the card.
     */
    abstract protected function calculation(): Calculation;

    /**
     * The ranges the card offers, in the order they are listed.
     *
     * @return list<MetricRange>
     */
    abstract protected function ranges(): array;

    /**
     * The range the card opens with.
     */
    abstract protected function defaultRange(): MetricRange;

    /**
     * The currency of the number. With a currency, the card shows an amount of money with two decimals and the currency symbol;
     * without one, it shows a plain number.
     */
    protected function currency(): ?Currency
    {
        return null;
    }

    /**
     * The full explanation of the card, shown in its info: what the number is and how it is calculated.
     */
    abstract protected function explanation(): string;

    /**
     * A simple example of the card's number, shown in its info under the explanation.
     */
    abstract protected function example(): string;

    /**
     * The chosen range when the card offers it, otherwise the default range.
     */
    protected function selectedRange(): MetricRange
    {
        return MetricRange::resolve($this->range, $this->ranges(), $this->defaultRange());
    }

    /**
     * The number of the card at the chosen range, formatted with its currency.
     */
    protected function formattedValue(float $raw): FormattedValue
    {
        return FormattedValue::of($raw, $this->currency());
    }

    /**
     * The card as the admin application shows it: its label, its number at the chosen range, the ranges it offers, and its info.
     *
     * @return array{label: string, value: array{number: string, currency: ?string, raw: float}, range: string, ranges: list<array{value: string, label: string}>, explanation: string, example: string}
     */
    public function toPayload(?string $range = null): array
    {
        $this->range = $range ?? $this->range ?? $this->defaultRange()->value;
        $selected = $this->selectedRange();
        $raw = $this->calculation()->calculate($selected->period());
        $value = $this->formattedValue($raw);

        return [
            'label' => $this->label(),
            'value' => ['number' => $value->number, 'currency' => $value->currency, 'raw' => $raw],
            'range' => $selected->value,
            'ranges' => array_map(fn (MetricRange $range): array => ['value' => $range->value, 'label' => $range->getLabel()], $this->ranges()),
            'explanation' => $this->explanation(),
            'example' => $this->example(),
        ];
    }
}
