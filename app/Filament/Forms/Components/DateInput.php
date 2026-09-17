<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;

/**
 * The date field used across the panel: a calendar (not the browser's native input) in the current language,
 * shown as year/month/day, with the week starting on Saturday, and closing as soon as a date is picked.
 */
class DateInput extends DatePicker
{
    protected function setUp(): void
    {
        parent::setUp();

        $this
            ->native(false)
            ->displayFormat('Y/m/d')
            ->firstDayOfWeek(6)
            ->closeOnDateSelection()
            ->suffixIcon(Heroicon::OutlinedCalendarDays, isInline: true)
            ->placeholder(__('filament/components/date_input.placeholder'));
    }

    /**
     * Uses today's date as the default value.
     */
    public function defaultToday(): static
    {
        return $this->default(fn (): string => today()->toDateString());
    }
}
