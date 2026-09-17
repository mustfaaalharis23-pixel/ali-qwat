<?php

namespace App\Filament\Widgets;

use App\Filament\Lenses\Lens;
use App\Metrics\FormattedValue;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;

/**
 * The count cards of the lenses a page watches: one card per lens the user can open, with the lens name and its count.
 * A lens with records needs attention: its card is colored and comes first, keeping the listed order. A card opens its lens.
 */
class LensCountCards extends Widget
{
    /**
     * The cards are not added to the home dashboard on their own; each page gives the lenses it watches.
     */
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.lens-count-cards';

    public int|string|array $columnSpan = 'full';

    /**
     * The watched lenses, in the order their cards are listed: LensCountCards::make(['lenses' => [ItemsWithoutSupplierLens::class]]).
     *
     * @var list<class-string<Lens>>
     */
    public array $lenses = [];

    /**
     * @return array{cards: Collection<int, array{key: string, label: string, explanation: string, guidance: string, number: string, url: string, needsAttention: bool}>}
     */
    protected function getViewData(): array
    {
        [$needAttention, $calm] = collect($this->lenses)
            ->filter(fn (string $lens): bool => $lens::canAccess())
            ->map(function (string $lens): array {
                $count = $lens::count();

                return [
                    'key' => $lens::uriKey(),
                    'label' => $lens::label(),
                    'explanation' => $lens::explanation(),
                    'guidance' => $lens::guidance(),
                    'number' => FormattedValue::of($count)->number,
                    'url' => $lens::getUrl(),
                    'needsAttention' => $count > 0,
                ];
            })
            ->partition(fn (array $card): bool => $card['needsAttention']);

        return [
            'cards' => $needAttention->merge($calm)->values(),
        ];
    }
}
