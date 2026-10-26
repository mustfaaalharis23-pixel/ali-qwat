<div style="display: flex; flex-wrap: wrap; justify-content: center; gap: 0.5rem 1.5rem">
    @foreach (collect(config('app.locales'))->except(app()->getLocale()) as $locale => $name)
        <x-filament::link :href="route('locale.switch', $locale)" icon="heroicon-o-language">
            {{ $name }}
        </x-filament::link>
    @endforeach
</div>
