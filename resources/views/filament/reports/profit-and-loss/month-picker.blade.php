{{--
    The period of the profit and loss report: one whole month.

    A closed field shows the chosen month. It opens on a small panel: the year with an arrow on each side of it,
    two shortcuts for the month that just ended and the month that is running, and the twelve months in a grid,
    each with its name and its number under it in a monospace font, so a month is found by its name or by its number.
    The month that is running has a dashed border, and a month that has not begun is there but cannot be pressed.
    Choosing a month closes the panel and fetches it at once, so there is no fetch button, and choosing the month that
    is already shown is how the report is refreshed.

    The years and the grid are walked through in the browser, so only choosing a month reaches the server.
    Every color is a Filament color variable, so the picker follows the panel's dark mode, and every side is logical,
    so it follows the right-to-left layout.
--}}
@php
    use Carbon\CarbonImmutable;

    $now = CarbonImmutable::now();
    $chosen = CarbonImmutable::parse($month.'-01');
@endphp

<style>
    /* Alpine hides the closed panel only after it starts, so it is hidden from the first paint. */
    .mp [x-cloak] { display: none !important; }

    .mp { position: relative; max-width: 20rem; }

    .mp-trigger {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid color-mix(in oklab, var(--gray-500) 30%, transparent);
        border-radius: 0.5rem;
        cursor: pointer;
        transition: 0.15s;
    }

    .mp-trigger:hover { border-color: color-mix(in oklab, var(--gray-500) 50%, transparent); }

    .mp-trigger[aria-expanded='true'] {
        border-color: var(--primary-500);
        box-shadow: 0 0 0 3px color-mix(in oklab, var(--primary-500) 20%, transparent);
    }

    .mp-arrow { font-size: 0.8rem; opacity: 0.6; transition: transform 0.2s; }
    .mp-trigger[aria-expanded='true'] .mp-arrow { transform: rotate(180deg); }

    .mp-panel {
        position: absolute;
        top: calc(100% + 0.375rem);
        inset-inline: 0;
        z-index: 30;
        border: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent);
        border-radius: 0.625rem;
        overflow: hidden;
        background: var(--gray-50);
        box-shadow: 0 8px 22px rgb(0 0 0 / 0.12), 0 2px 5px rgb(0 0 0 / 0.05);
    }

    :is(.dark) .mp-panel { background: var(--gray-900); }

    .mp-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.5rem 0.75rem;
        border-bottom: 1px solid color-mix(in oklab, var(--gray-500) 20%, transparent);
    }

    .mp-year {
        font-family: 'Courier New', monospace;
        font-size: 0.95rem;
        font-weight: 700;
    }

    .mp-nav {
        width: 1.5rem;
        height: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent);
        border-radius: 0.375rem;
        font-weight: 700;
        transition: 0.15s;
    }

    .mp-nav:hover:not(:disabled) { background: var(--primary-500); border-color: var(--primary-500); color: var(--gray-950); }
    .mp-nav:disabled { opacity: 0.35; cursor: not-allowed; }

    .mp-shortcuts {
        display: flex;
        gap: 0.25rem;
        padding: 0.375rem 0.5rem;
        border-bottom: 1px solid color-mix(in oklab, var(--gray-500) 15%, transparent);
    }

    .mp-shortcut {
        flex: 1;
        padding: 0.25rem 0.375rem;
        border: 1px solid color-mix(in oklab, var(--gray-500) 25%, transparent);
        border-radius: 0.375rem;
        font-size: 0.7rem;
        font-weight: 600;
        white-space: nowrap;
        transition: 0.15s;
    }

    .mp-shortcut:hover { background: color-mix(in oklab, var(--primary-500) 20%, transparent); border-color: var(--primary-500); }

    .mp-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.3rem;
        padding: 0.5rem;
    }

    .mp-month {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.05rem;
        min-height: 2.75rem;
        padding: 0.375rem 0.25rem;
        border: 1.5px solid color-mix(in oklab, var(--gray-500) 25%, transparent);
        border-radius: 0.45rem;
        transition: 0.15s;
    }

    .mp-month-name { font-size: 0.75rem; font-weight: 600; line-height: 1.1; }
    .mp-month-number { font-family: 'Courier New', monospace; font-size: 0.6rem; opacity: 0.6; }

    .mp-month:hover:not(:disabled):not([aria-pressed='true']) {
        border-color: var(--primary-500);
        background: color-mix(in oklab, var(--primary-500) 12%, transparent);
    }

    .mp-month[aria-pressed='true'] {
        background: var(--primary-500);
        border-color: var(--primary-500);
        color: var(--gray-950);
    }

    .mp-month[aria-pressed='true'] .mp-month-number { opacity: 0.75; }

    .mp-month-current:not([aria-pressed='true']) {
        border-style: dashed;
        border-color: var(--primary-500);
    }

    .mp-month:disabled {
        border-style: dashed;
        opacity: 0.4;
        cursor: not-allowed;
    }

    .mp-foot {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.5rem;
        padding: 0.375rem 0.75rem;
        border-top: 1px solid color-mix(in oklab, var(--gray-500) 20%, transparent);
    }

    .mp-chosen { font-size: 0.72rem; opacity: 0.75; }

    .mp-done {
        padding: 0.2rem 0.9rem;
        border-radius: 0.375rem;
        background: var(--primary-500);
        color: var(--gray-950);
        font-size: 0.72rem;
        font-weight: 700;
    }
</style>

<div
    class="mp"
    x-data="{
        open: false,
        viewYear: {{ $chosen->year }},
        selected: @js($month),
        names: @js($monthNames),
        firstYear: {{ $firstYear }},
        thisYear: {{ $now->year }},
        thisMonth: {{ $now->month }},
        key(year, month) { return year + '-' + String(month).padStart(2, '0') },
        isSelected(year, month) { return this.selected === this.key(year, month) },
        isThisMonth(year, month) { return year === this.thisYear && month === this.thisMonth },
        isToCome(year, month) { return year > this.thisYear || (year === this.thisYear && month > this.thisMonth) },
        label(key) { return this.names[Number(key.slice(5)) - 1] + ' ' + key.slice(0, 4) },
        pick(year, month) {
            if (this.isToCome(year, month)) return

            this.selected = this.key(year, month)
            this.viewYear = year
            this.open = false

            $wire.selectMonth(this.selected)
        },
    }"
    x-on:keydown.escape="open = false"
>
    <div
        class="mp-trigger"
        role="button"
        tabindex="0"
        aria-haspopup="dialog"
        x-bind:aria-expanded="open"
        x-bind:aria-label="@js(__('filament/reports/profit_and_loss.picker.label')) + ': ' + label(selected)"
        x-on:click="open = ! open; viewYear = Number(selected.slice(0, 4))"
        x-on:keydown.enter.prevent="open = ! open"
        x-on:keydown.space.prevent="open = ! open"
    >
        <span style="display: flex; flex-direction: column; gap: 0.1rem; text-align: start">
            <span style="font-size: 0.7rem; font-weight: 600; color: var(--gray-500)">
                {{ __('filament/reports/profit_and_loss.picker.label') }}
            </span>
            <span style="font-size: 0.95rem; font-weight: 600" x-text="label(selected)"></span>
        </span>

        <span class="mp-arrow">▾</span>
    </div>

    <div
        class="mp-panel"
        role="dialog"
        aria-label="{{ __('filament/reports/profit_and_loss.picker.label') }}"
        x-show="open"
        x-cloak
        x-on:click.outside="open = false"
        x-transition.opacity.duration.150ms
    >
        <div class="mp-head">
            <button
                type="button"
                class="mp-nav"
                x-bind:disabled="viewYear <= firstYear"
                x-on:click="viewYear--"
                aria-label="{{ __('filament/reports/profit_and_loss.picker.earlier_year') }}"
            >−</button>

            <span class="mp-year" x-text="viewYear"></span>

            <button
                type="button"
                class="mp-nav"
                x-bind:disabled="viewYear >= thisYear"
                x-on:click="viewYear++"
                aria-label="{{ __('filament/reports/profit_and_loss.picker.later_year') }}"
            >+</button>
        </div>

        <div class="mp-shortcuts">
            <button type="button" class="mp-shortcut" x-on:click="pick({{ $now->subMonth()->year }}, {{ $now->subMonth()->month }})">
                {{ __('filament/reports/profit_and_loss.picker.last_month') }}
            </button>
            <button type="button" class="mp-shortcut" x-on:click="pick({{ $now->year }}, {{ $now->month }})">
                {{ __('filament/reports/profit_and_loss.picker.this_month') }}
            </button>
        </div>

        <div class="mp-grid">
            <template x-for="number in 12" x-bind:key="number">
                <button
                    type="button"
                    class="mp-month"
                    x-bind:class="{ 'mp-month-current': isThisMonth(viewYear, number) }"
                    x-bind:disabled="isToCome(viewYear, number)"
                    x-bind:aria-pressed="isSelected(viewYear, number)"
                    x-bind:aria-label="names[number - 1] + ' ' + viewYear"
                    x-on:click="pick(viewYear, number)"
                >
                    <span class="mp-month-name" x-text="names[number - 1]"></span>
                    <span class="mp-month-number" x-text="String(number).padStart(2, '0')"></span>
                </button>
            </template>
        </div>

        <div class="mp-foot">
            <span class="mp-chosen" x-text="label(selected)"></span>

            <button type="button" class="mp-done" x-on:click="open = false">
                {{ __('filament/reports/profit_and_loss.picker.done') }}
            </button>
        </div>
    </div>
</div>
