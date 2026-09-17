{{--
    The account statement tab: the customer and period form on top, the summary cards of the period under it, then the statement table.
    The cards reuse the colored totals of the reports, so they follow the panel's dark mode and right-to-left layout.
--}}
<x-filament-widgets::widget class="fi-wi-table">
    <div style="display: flex; flex-direction: column; gap: 1.5rem">
        <x-filament::section>
            {{ $this->form }}
        </x-filament::section>

        @if ($summary !== [])
            @include('filament.reports.totals', ['totals' => $summary])
        @endif

        {{ $this->table }}
    </div>
</x-filament-widgets::widget>
