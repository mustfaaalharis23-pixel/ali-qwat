<?php

namespace App\Filament\Forms\Components;

use Carbon\CarbonImmutable;
use Filament\Forms\Components\Field;

/**
 * One field for a period: one calendar, and a checkbox under it. Unticked, which is a single day, one click chooses
 * the day; ticked, the first day clicked opens the period and the next one closes it, in the same calendar.
 * A day after today is never chosen, and ticking the box back and forth never loses the other choice.
 *
 * Its state is ['from' => 'Y-m-d', 'until' => 'Y-m-d'].
 */
class PeriodInput extends Field
{
    protected string $view = 'filament.forms.components.period-input';

    /**
     * The period the field starts on: from the day this many months ago, until today.
     */
    public function defaultLastMonths(int $months): static
    {
        return $this->default(fn (): array => [
            'from' => CarbonImmutable::today()->subMonths($months)->toDateString(),
            'until' => CarbonImmutable::today()->toDateString(),
        ]);
    }

    /**
     * The words the field says, in the current language: what it says before a period is chosen, and the line under
     * the checkbox that tells what will be fetched.
     *
     * @return array<string, string>
     */
    public function getWords(): array
    {
        return [
            'placeholder' => __('filament/reports.period.placeholder'),
            'none' => __('filament/reports.period.none'),
            'single' => __('filament/reports.period.single'),
            'range' => __('filament/reports.period.chosen'),
            'incomplete' => __('filament/reports.period.incomplete'),
        ];
    }

    /**
     * The names of the twelve months in the current language.
     *
     * @return list<string>
     */
    public function getMonthNames(): array
    {
        return collect(range(1, 12))
            ->map(fn (int $month): string => CarbonImmutable::create(2000, $month, 1)->translatedFormat('F'))
            ->all();
    }

    /**
     * The short names of the seven week days, starting on Saturday like the rest of the panel's calendars.
     * The first day of the year 2000 was a Saturday.
     *
     * @return list<string>
     */
    public function getWeekdayNames(): array
    {
        $saturday = CarbonImmutable::create(2000, 1, 1);

        return collect(range(0, 6))
            ->map(fn (int $day): string => $saturday->addDays($day)->translatedFormat('D'))
            ->all();
    }
}
