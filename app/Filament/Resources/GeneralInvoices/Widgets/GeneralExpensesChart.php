<?php

namespace App\Filament\Resources\GeneralInvoices\Widgets;

use App\Models\Invoice;
use Filament\Widgets\ChartWidget;

/**
 * A pie of where the general expenses went: the descriptions spent on the most, each with its own color,
 * and the rest of them together as one gray part. The purchases are not in it, they have their own screen.
 */
class GeneralExpensesChart extends ChartWidget
{
    /**
     * The colors of the parts, from the largest expense to the smallest.
     *
     * @var list<string>
     */
    private const COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#ef4444', '#8b5cf6'];

    private const OTHERS_COLOR = '#9ca3af';

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    /**
     * The pie keeps a calm height, so a wide card does not turn it into a giant circle.
     */
    protected ?string $maxHeight = '260px';

    public function getHeading(): string
    {
        return __('filament/resources/general_invoice.widgets.expenses_chart.heading');
    }

    protected function getType(): string
    {
        return 'pie';
    }

    /**
     * @return array{datasets: list<array{label: string, data: list<float>, backgroundColor: list<string>}>, labels: list<string>}
     */
    protected function getData(): array
    {
        $expenses = Invoice::generalExpensesByDescription();

        $labels = array_column($expenses['descriptions'], 'name');
        $data = array_column($expenses['descriptions'], 'expense');
        $colors = array_slice(self::COLORS, 0, count($data));

        if ($expenses['others'] > 0) {
            $labels[] = __('filament/resources/general_invoice.widgets.expenses_chart.others');
            $data[] = $expenses['others'];
            $colors[] = self::OTHERS_COLOR;
        }

        return [
            'datasets' => [
                [
                    'label' => $this->getHeading(),
                    'data' => $data,
                    'backgroundColor' => $colors,
                ],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * A pie has no axes.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
        ];
    }
}
