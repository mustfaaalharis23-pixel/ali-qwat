@props([
    'id',
    'heading',
    'explanation',
    'noteTitle',
    'note',
])

{{--
    The info icon of a card and the modal it opens: the card's explanation, then a highlighted note under it,
    such as a simple example on a metric card, or what to do on a lens count card.
--}}
<x-filament::modal
    :id="$id"
    :heading="$heading"
    :icon="\Filament\Support\Icons\Heroicon::OutlinedInformationCircle"
    teleport="body"
    width="lg"
>
    <x-slot name="trigger">
        <x-filament::icon-button
            :icon="\Filament\Support\Icons\Heroicon::OutlinedInformationCircle"
            :label="__('metrics.info.open')"
            color="gray"
            size="sm"
        />
    </x-slot>

    <div style="display: grid; row-gap: 1.25rem">
        <div style="display: grid; row-gap: 0.375rem">
            <h3 style="font-weight: 600">
                {{ __('metrics.info.explanation') }}
            </h3>

            <p style="line-height: 1.75rem">
                {{ $explanation }}
            </p>
        </div>

        <div style="display: grid; row-gap: 0.375rem; border-inline-start: 3px solid var(--primary-500); padding-inline-start: 0.75rem">
            <h3 style="font-weight: 600">
                {{ $noteTitle }}
            </h3>

            <p style="line-height: 1.75rem">
                {{ $note }}
            </p>
        </div>
    </div>
</x-filament::modal>
