{{--
    The period field: one box with one calendar, and a checkbox under it. Unticked the box is a single day and one
    click chooses it; ticked it is a period, the first day clicked opens it and the next one closes it. Under the
    checkbox one line says what will be fetched, and warns while a period has no end yet.
    A day after today is never chosen, and switching the mode never loses the other choice.
    Everything is drawn with Filament's own classes and color variables, so it follows the panel's dark mode and its
    right-to-left layout.
--}}
@once
    @push('styles')
        <style>
            .waqt-period-trigger {
                width: 100%;
                cursor: pointer;
                text-align: start;
            }

            .waqt-period-trigger:hover {
                --tw-ring-color: color-mix(in oklab, var(--gray-500) 35%, transparent);
            }

            .waqt-period-icon {
                width: 1.25rem;
                height: 1.25rem;
                flex-shrink: 0;
            }

            .waqt-period-value {
                display: flex;
                align-items: center;
                min-height: 2.25rem;
                overflow: hidden;
            }

            .waqt-period-label {
                font-size: 0.875rem;
                line-height: 1.5rem;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
                unicode-bidi: isolate;
                font-variant-numeric: tabular-nums;
            }

            .waqt-period-panel {
                padding: 0.875rem;
            }

            .waqt-period-header {
                display: flex;
                align-items: center;
                gap: 0.25rem;
                margin-bottom: 0.625rem;
            }

            .waqt-period-month {
                flex: 1;
                text-align: center;
                font-size: 0.875rem;
                font-weight: 600;
                white-space: nowrap;
            }

            .waqt-period-move {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 1.875rem;
                height: 1.875rem;
                flex-shrink: 0;
                border-radius: 0.5rem;
                color: var(--gray-500);
                transition: background-color 75ms;
            }

            .waqt-period-move:hover {
                background-color: color-mix(in oklab, var(--gray-500) 12%, transparent);
                color: inherit;
            }

            .waqt-period-grid {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 0.125rem;
                text-align: center;
            }

            .waqt-period-weekday {
                padding: 0.25rem 0;
                font-size: 0.6875rem;
                font-weight: 500;
                color: var(--gray-500);
            }

            .waqt-period-day {
                width: 100%;
                height: 2rem;
                border-radius: 0.5rem;
                font-size: 0.8125rem;
                font-variant-numeric: tabular-nums;
                transition: background-color 75ms;
            }

            .waqt-period-day:hover:not(:disabled) {
                background-color: color-mix(in oklab, var(--gray-500) 12%, transparent);
            }

            .waqt-period-footer {
                margin-top: 0.625rem;
                display: flex;
                align-items: center;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 0.25rem 0.75rem;
            }

            .waqt-period-range {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.8125rem;
                font-weight: 500;
                cursor: pointer;
            }

            .waqt-period-hint {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 0.35rem;
                font-size: 0.6875rem;
                color: var(--gray-500);
                text-align: end;
            }

            .waqt-period-hint-dates {
                unicode-bidi: isolate;
                font-variant-numeric: tabular-nums;
            }
        </style>
    @endpush
@endonce

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            state: $wire.$entangle('{{ $getStatePath() }}'),
            months: @js($getMonthNames()),
            weekdays: @js($getWeekdayNames()),
            words: @js($getWords()),
            isRange: false,
            rememberedUntil: null,
            year: null,
            month: null,
            pending: null,

            init() {
                this.isRange = (this.state?.from ?? null) !== (this.state?.until ?? null);
                this.show(this.state?.from ?? this.today());
            },

            today() {
                return this.stamp(new Date());
            },

            stamp(date) {
                return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
            },

            parse(day) {
                const [year, month, date] = day.split('-').map(Number);

                return new Date(year, month - 1, date);
            },

            show(day) {
                const date = this.parse(day);

                this.year = date.getFullYear();
                this.month = date.getMonth();
            },

            written(day) {
                return day ? day.replaceAll('-', '/') : '';
            },

            get label() {
                const from = this.state?.from;
                const until = this.state?.until;

                if (! from) {
                    return this.words.placeholder;
                }

                return (this.isRange && until && until !== from)
                    ? this.written(from) + ' - ' + this.written(until)
                    : this.written(from);
            },

            get incomplete() {
                return this.isRange && (this.state?.until ?? null) === (this.state?.from ?? null);
            },

            get hintWords() {
                if (! this.state?.from) {
                    return this.words.none;
                }

                if (! this.isRange) {
                    return this.words.single;
                }

                return this.incomplete ? this.words.incomplete : this.words.range;
            },

            get hintDates() {
                if (! this.state?.from || this.incomplete) {
                    return '';
                }

                return this.isRange
                    ? this.written(this.state.from) + ' → ' + this.written(this.state.until)
                    : this.written(this.state.from);
            },

            get monthLabel() {
                return this.months[this.month] + ' ' + this.year;
            },

            get days() {
                const first = new Date(this.year, this.month, 1);
                const offset = (first.getDay() + 1) % 7;
                const total = new Date(this.year, this.month + 1, 0).getDate();
                const days = Array(offset).fill(null);

                for (let day = 1; day <= total; day++) {
                    days.push(this.stamp(new Date(this.year, this.month, day)));
                }

                return days;
            },

            move(months) {
                const moved = new Date(this.year, this.month + months, 1);

                this.year = moved.getFullYear();
                this.month = moved.getMonth();
            },

            switchMode() {
                this.pending = null;

                if (! this.isRange) {
                    this.rememberedUntil = this.state?.until ?? null;
                    this.state = { from: this.state?.from ?? this.today(), until: this.state?.from ?? this.today() };

                    return;
                }

                if (this.rememberedUntil && this.rememberedUntil > (this.state?.from ?? '')) {
                    this.state = { from: this.state.from, until: this.rememberedUntil };
                }
            },

            pick(day) {
                if (day > this.today()) {
                    return;
                }

                if (! this.isRange) {
                    this.state = { from: day, until: day };
                    this.close();

                    return;
                }

                if (this.pending === null) {
                    this.pending = day;
                    this.state = { from: day, until: day };

                    return;
                }

                this.state = day < this.pending
                    ? { from: day, until: this.pending }
                    : { from: this.pending, until: day };

                this.pending = null;
                this.close();
            },

            isEdge(day) {
                return day === this.state?.from || (this.isRange && day === this.state?.until);
            },

            isInside(day) {
                return this.isRange && this.state?.from && this.state?.until && day > this.state.from && day < this.state.until;
            },

            dayStyle(day) {
                if (day > this.today()) {
                    return { opacity: '0.3', cursor: 'not-allowed' };
                }

                if (this.isEdge(day)) {
                    return { background: 'var(--primary-600)', color: 'white', fontWeight: '600' };
                }

                if (this.isInside(day)) {
                    return { background: 'color-mix(in oklab, var(--primary-500) 18%, transparent)' };
                }

                if (day === this.today()) {
                    return { boxShadow: 'inset 0 0 0 1px color-mix(in oklab, var(--gray-500) 45%, transparent)' };
                }

                return {};
            },

            get flipped() {
                return document.documentElement.dir === 'rtl' ? 'scaleX(-1)' : null;
            },
        }"
    >
        <x-filament::dropdown width="xs" placement="bottom-start">
            <x-slot name="trigger">
                <button
                    type="button"
                    class="fi-input-wrp waqt-period-trigger"
                    x-on:click="pending = null; show(state?.from ?? today())"
                >
                    <span class="fi-input-wrp-prefix fi-input-wrp-prefix-has-content fi-inline">
                        @svg('heroicon-o-calendar-days', 'fi-icon waqt-period-icon')
                    </span>

                    <span class="fi-input-wrp-content-ctn waqt-period-value">
                        <span
                            dir="ltr"
                            class="waqt-period-label"
                            :style="state?.from ? {} : { color: 'var(--gray-400)' }"
                            x-text="label"
                        ></span>
                    </span>

                    <span class="fi-input-wrp-suffix fi-inline">
                        @svg('heroicon-m-chevron-down', 'fi-icon waqt-period-icon')
                    </span>
                </button>
            </x-slot>

            <div class="waqt-period-panel">
                <div class="waqt-period-header">
                    <button type="button" class="waqt-period-move" x-on:click="move(-1)">
                        <span :style="{ transform: flipped, display: 'block' }">
                            @svg('heroicon-m-chevron-left', 'waqt-period-icon')
                        </span>
                    </button>

                    <span class="waqt-period-month" x-text="monthLabel"></span>

                    <button type="button" class="waqt-period-move" x-on:click="move(1)">
                        <span :style="{ transform: flipped, display: 'block' }">
                            @svg('heroicon-m-chevron-right', 'waqt-period-icon')
                        </span>
                    </button>
                </div>

                <div class="waqt-period-grid">
                    <template x-for="weekday in weekdays" :key="weekday">
                        <div class="waqt-period-weekday" x-text="weekday"></div>
                    </template>

                    <template x-for="(day, index) in days" :key="index">
                        <div>
                            <template x-if="day">
                                <button
                                    type="button"
                                    class="waqt-period-day"
                                    :style="dayStyle(day)"
                                    :disabled="day > today()"
                                    x-on:click="pick(day)"
                                    x-text="Number(day.slice(8))"
                                ></button>
                            </template>
                        </div>
                    </template>
                </div>
            </div>
        </x-filament::dropdown>

        <div class="waqt-period-footer">
            <label class="waqt-period-range">
                <input type="checkbox" class="fi-checkbox-input" x-model="isRange" x-on:change="switchMode()" />

                <span>{{ __('filament/reports.period.range') }}</span>
            </label>

            <div
                class="waqt-period-hint"
                :style="incomplete ? { color: 'var(--warning-600)', fontWeight: '600', opacity: '1' } : {}"
            >
                <span x-text="hintWords"></span>

                <span dir="ltr" class="waqt-period-hint-dates" x-text="hintDates"></span>
            </div>
        </div>
    </div>
</x-dynamic-component>
