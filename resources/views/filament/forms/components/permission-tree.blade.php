@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            selected() {
                return Array.isArray(this.state) ? this.state : []
            },
            has(permission) {
                return this.selected().includes(permission)
            },
            toggle(permission) {
                this.state = this.has(permission)
                    ? this.selected().filter((selected) => selected !== permission)
                    : [...this.selected(), permission]
            },
            countIn(permissions) {
                return permissions.filter((permission) => this.has(permission)).length
            },
            toggleGroup(permissions) {
                const others = this.selected().filter((selected) => ! permissions.includes(selected))

                this.state = this.countIn(permissions) === permissions.length
                    ? others
                    : [...others, ...permissions]
            },
        }"
        {{ $getExtraAttributeBag()->class(['fi-permission-tree']) }}
        style="display: grid; gap: 0.5rem"
    >
        @foreach ($getGroups() as $group)
            <div
                x-data="{ permissions: @js(array_keys($group['permissions'])), expanded: true }"
                class="fi-permission-tree-group"
            >
                <div style="display: flex; align-items: center; gap: 0.5rem; padding-block: 0.25rem">
                    <button
                        type="button"
                        class="fi-icon-btn fi-size-sm"
                        x-on:click="expanded = ! expanded"
                        x-bind:aria-expanded="expanded"
                        x-bind:style="expanded ? '' : 'transform: rotate(' + (document.documentElement.dir === 'rtl' ? '90' : '-90') + 'deg)'"
                        style="transition: transform 150ms"
                    >
                        {{ \Filament\Support\generate_icon_html(Heroicon::ChevronDown) }}
                    </button>

                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-weight: 600">
                        <input
                            type="checkbox"
                            class="fi-checkbox-input fi-valid"
                            x-bind:checked="countIn(permissions) === permissions.length"
                            x-effect="$el.indeterminate = countIn(permissions) > 0 && countIn(permissions) < permissions.length"
                            x-on:change="toggleGroup(permissions)"
                        />

                        <span>{{ $group['label'] }}</span>
                    </label>

                    <span
                        x-text="countIn(permissions) + ' / ' + permissions.length"
                        style="font-size: 0.75rem; opacity: 0.6"
                    ></span>
                </div>

                <div
                    x-show="expanded"
                    style="display: grid; gap: 0.25rem; margin-inline-start: 1rem; padding-inline-start: 1.5rem; border-inline-start: 1px solid color-mix(in oklab, currentColor 15%, transparent)"
                >
                    @foreach ($group['permissions'] as $permission => $label)
                        <label style="display: flex; align-items: center; gap: 0.75rem; padding-block: 0.25rem; cursor: pointer">
                            <input
                                type="checkbox"
                                class="fi-checkbox-input fi-valid"
                                value="{{ $permission }}"
                                x-bind:checked="has(@js($permission))"
                                x-on:change="toggle(@js($permission))"
                            />

                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-dynamic-component>
