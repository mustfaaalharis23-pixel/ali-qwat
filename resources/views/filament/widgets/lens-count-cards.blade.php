{{--
    The count cards of the watched lenses: the lens name with its info icon, and its count in the middle.
    A card whose lens has records is fully red, blinks softly to draw attention, and comes first.
    A click on the card opens its lens; the info icon opens a modal with what the lens shows and what to do.
    It uses Filament's own stat card classes, so it follows the panel's colors, dark mode, and right-to-left layout.
--}}
<x-filament-widgets::widget class="fi-wi-lens-count-cards">
    <style>
        @keyframes fi-lens-count-card-blink {
            0%, 100% {
                background-color: var(--danger-600);
                box-shadow: 0 0 0 0 color-mix(in oklab, var(--danger-500) 70%, transparent);
            }

            50% {
                background-color: var(--danger-500);
                box-shadow: 0 0 0 0.5rem transparent;
            }
        }

        .fi-wi-lens-count-cards .fi-wi-stats-overview-stat.fi-lens-count-card {
            position: relative;
        }

        .fi-wi-lens-count-cards .fi-wi-stats-overview-stat.fi-lens-count-card-attention {
            background-color: var(--danger-600);
            animation: fi-lens-count-card-blink 1.6s ease-in-out infinite;
        }

        .fi-wi-lens-count-cards .fi-lens-count-card-attention .fi-wi-stats-overview-stat-label,
        .fi-wi-lens-count-cards .fi-lens-count-card-attention .fi-wi-stats-overview-stat-value,
        .fi-wi-lens-count-cards .fi-lens-count-card-attention .fi-icon-btn {
            color: #fff;
        }

        .fi-wi-lens-count-cards .fi-lens-count-card-link {
            position: absolute;
            inset: 0;
            border-radius: inherit;
        }

        .fi-wi-lens-count-cards .fi-lens-count-card-info {
            position: relative;
            z-index: 1;
        }

        @media (prefers-reduced-motion: reduce) {
            .fi-wi-lens-count-cards .fi-wi-stats-overview-stat.fi-lens-count-card-attention {
                animation: none;
            }
        }
    </style>

    @if ($cards->isEmpty())
        <div class="fi-wi-stats-overview-stat">
            <span class="fi-wi-stats-overview-stat-label">
                {{ __('filament/pages/monitoring_dashboard.empty') }}
            </span>
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(16rem, 1fr)); gap: 1.5rem">
            @foreach ($cards as $card)
                <div
                    @class([
                        'fi-wi-stats-overview-stat',
                        'fi-lens-count-card',
                        'fi-lens-count-card-attention' => $card['needsAttention'],
                    ])
                >
                    <a
                        href="{{ $card['url'] }}"
                        class="fi-lens-count-card-link"
                        aria-label="{{ $card['label'] }}"
                    ></a>

                    <div class="fi-wi-stats-overview-stat-content" style="row-gap: 1rem">
                        <div style="display: flex; align-items: center; gap: 0.25rem; min-width: 0">
                            <span class="fi-wi-stats-overview-stat-label" style="min-width: 0; font-size: 1.125rem; line-height: 1.5rem">
                                {{ $card['label'] }}
                            </span>

                            <span class="fi-lens-count-card-info">
                                <x-info-modal
                                    :id="$this->getId() . '-' . $card['key'] . '-info'"
                                    :heading="$card['label']"
                                    :explanation="$card['explanation']"
                                    :note-title="__('filament/lenses.info.what_to_do')"
                                    :note="$card['guidance']"
                                />
                            </span>
                        </div>

                        <span class="fi-wi-stats-overview-stat-value" style="justify-self: center; font-size: 2.25rem; line-height: 1">
                            {{ $card['number'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</x-filament-widgets::widget>
