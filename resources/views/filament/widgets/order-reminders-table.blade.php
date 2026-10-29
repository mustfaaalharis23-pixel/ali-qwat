{{--
    The weekly payment reminders on the home dashboard: the tabs on top (due today, late, all), each with how many orders it has,
    then the table of the tab's customers and their orders.

    $counts: how many orders each tab has (OrderRemindersTable::getViewData)
--}}
<x-filament-widgets::widget class="fi-wi-table">
    <div style="display: grid; gap: 1rem">
        <x-filament::tabs>
            <x-filament::tabs.item :active="$this->tab === 'today'" wire:click="$set('tab', 'today')" icon="heroicon-o-bell-alert"
                :badge="$counts['today'] ?: null" badge-color="warning">
                {{ __('filament/pages/customer_payments.order_reminders.tab_today') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$this->tab === 'late'" wire:click="$set('tab', 'late')" icon="heroicon-o-exclamation-triangle"
                :badge="$counts['late'] ?: null" badge-color="danger">
                {{ __('filament/pages/customer_payments.order_reminders.tab_late') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item :active="$this->tab === 'all'" wire:click="$set('tab', 'all')" icon="heroicon-o-queue-list"
                :badge="$counts['all'] ?: null">
                {{ __('filament/pages/customer_payments.order_reminders.tab_all') }}
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{ $this->table }}
    </div>
</x-filament-widgets::widget>
