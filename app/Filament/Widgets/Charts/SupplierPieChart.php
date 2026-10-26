<?php

namespace App\Filament\Widgets\Charts;

use Filament\Widgets\ChartWidget;

/**
 * The template of the pies of a number for each supplier, from the largest to the smallest:
 * each of the largest suppliers gets a color, and the rest are one gray part named others.
 * A pie only gives its heading and its parts.
 */
abstract class SupplierPieChart extends ChartWidget
{
    /**
     * The colors of the supplier parts, in order.
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

    /**
     * The heading shown above the pie.
     */
    abstract protected function heading(): string;

    /**
     * The largest suppliers by name and value, and the sum of the rest.
     *
     * @return array{suppliers: list<array{name: string, value: float}>, others: float}
     */
    abstract protected function parts(): array;

    public function getHeading(): string
    {
        return $this->heading();
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
        $parts = $this->parts();

        $labels = array_column($parts['suppliers'], 'name');
        $data = array_column($parts['suppliers'], 'value');
        $colors = array_slice(self::COLORS, 0, count($data));

        if ($parts['others'] > 0) {
            $labels[] = __('filament/components/supplier_pie_chart.others');
            $data[] = $parts['others'];
            $colors[] = self::OTHERS_COLOR;
        }

        return [
            'datasets' => [
                [
                    'label' => $this->heading(),
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
