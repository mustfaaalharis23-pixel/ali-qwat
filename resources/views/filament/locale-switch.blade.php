@foreach (collect(config('app.locales'))->except(app()->getLocale()) as $locale => $name)
    <div style="text-align: center">
        <x-filament::link :href="route('locale.switch', $locale)" icon="heroicon-o-language">
            {{ $name }}
        </x-filament::link>
    </div>
@endforeach
