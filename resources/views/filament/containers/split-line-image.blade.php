{{--
    The item's image on a line of the "add part of an invoice" window, large, so the right line is not mistaken.
    A click opens it full size in a new tab. A grey box with a photo icon when the item has no image.

    $url: ?string, $alt: string
--}}
@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener" style="display: block">
        <img
            src="{{ $url }}"
            alt="{{ $alt }}"
            style="display: block; width: 10rem; height: 10rem; object-fit: cover; border-radius: 0.75rem"
        />
    </a>
@else
    <div style="display: flex; align-items: center; justify-content: center; width: 10rem; height: 10rem; border-radius: 0.75rem; background: rgba(127, 127, 127, 0.12)">
        <x-filament::icon :icon="\Filament\Support\Icons\Heroicon::OutlinedPhoto" style="width: 3rem; height: 3rem; opacity: 0.5" />
    </div>
@endif
