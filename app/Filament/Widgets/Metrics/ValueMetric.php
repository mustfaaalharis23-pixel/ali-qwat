<?php

namespace App\Filament\Widgets\Metrics;

use App\Metrics\Calculation;
use App\Metrics\Currency;
use App\Metrics\FormattedValue;
use App\Metrics\MetricRange;
use Filament\Widgets\Widget;
use Livewire\Attributes\On;

/**
 * The template of every value metric card: a label, one number, and a list of ranges to choose from.
 * A card gives its label, its calculation, its ranges, its default range, and its info: an explanation and a simple example.
 * The info is required, so no card can be built without it. The look, the info modal, the ranges, and the dates come from here.
 */
abstract class ValueMetric extends Widget
{
    /**
     * The browser event sent when money moves between boxes on the page, such as a cashier money transfer.
     */
    public const MONEY_MOVED_EVENT = 'money-moved';

    /**
     * Metric cards are not added to the home dashboard on their own; each page lists the cards it shows.
     */
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.value-metric';

    /**
     * The card's width on the page grid, set from the page: InvoiceCountMetric::make(['columnSpan' => 2]),
     * or per screen size: InvoiceCountMetric::make(['columnSpan' => ['md' => 2, 'xl' => 1]]), or 'full'.
     *
     * @var int|string|array<string, int|null>
     */
    public int|string|array $columnSpan = 1;

    /**
     * The value of the range chosen in the browser. It is checked against the card's ranges before it is used.
     */
    public ?string $range = null;

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
     * The full explanation of the card, shown in its info modal: what the number is and how it is calculated.
     */
    abstract protected function explanation(): string;

    /**
     * A simple example of the card's number, shown in its info modal under the explanation.
     */
    abstract protected function example(): string;

    /**
     * Shows the card again with a fresh number when money moves between boxes on the page, without reloading the page.
     */
    #[On(self::MONEY_MOVED_EVENT)]
    public function refreshAfterMoneyMoved(): void {}

    public function mount(): void
    {
        $this->range = $this->defaultRange()->value;
    }

    /**
     * The chosen range when the card offers it, otherwise the default range.
     */
    protected function selectedRange(): MetricRange
    {
        return MetricRange::resolve($this->range, $this->ranges(), $this->defaultRange());
    }

    /**
     * @return array{label: string, value: FormattedValue, ranges: list<MetricRange>, explanation: string, example: string}
     */
    protected function getViewData(): array
    {
        return [
            'label' => $this->label(),
            'value' => FormattedValue::of($this->calculation()->calculate($this->selectedRange()->period()), $this->currency()),
            'ranges' => $this->ranges(),
            'explanation' => $this->explanation(),
            'example' => $this->example(),
        ];
    }
}
