@php
    $items = \Wyvern\Navigation\CommandItems::all();

    // A Blade icon component cannot be rendered inside an Alpine x-for — the rows are
    // plain JS objects by then. So the distinct icons are rendered once, server-side, and
    // the rows reference them by name. Deduplicating matters: sixty rows share about a
    // dozen glyphs, and inlining the SVG per row would ship the same markup over and over.
    // The name is pattern-checked because it reaches Blade::render().
    $icons = collect($items)
        ->pluck('icon')
        ->unique()
        ->filter(fn (string $icon) => (bool) preg_match('/^[a-z0-9-]+$/', $icon))
        ->mapWithKeys(fn (string $icon) => [
            $icon => \Illuminate\Support\Facades\Blade::render(
                '<x-filament::icon :icon="$icon" />',
                ['icon' => $icon],
            ),
        ]);
@endphp

@if (filled($items))
    {{-- Alpine, not Livewire, and no network on keystroke: the items are in the DOM and
         the filter runs locally. See Wyvern\Navigation\CommandItems for why. --}}
    <div
        x-data="{
            open: false,
            query: '',
            active: 0,

            items: @js($items),
            icons: @js($icons),

            get results() {
                const q = this.query.trim().toLowerCase();

                if (! q) {
                    return this.items.slice(0, 12);
                }

                // Every word has to appear somewhere in the row, in any order, so
                // 'mine con' finds the console of a server called Minecraft.
                const words = q.split(/\s+/);

                return this.items.filter((item) => {
                    const haystack = (item.label + ' ' + (item.sublabel ?? '') + ' ' + item.group).toLowerCase();

                    return words.every((word) => haystack.includes(word));
                }).slice(0, 20);
            },

            show() {
                this.open = true;
                this.query = '';
                this.active = 0;
                this.$nextTick(() => this.$refs.input?.focus());
            },

            hide() {
                this.open = false;
            },

            move(delta) {
                const count = this.results.length;

                if (! count) {
                    return;
                }

                this.active = (this.active + delta + count) % count;
                this.$nextTick(() => {
                    this.$refs.list?.querySelectorAll('[data-wy-cmd-item]')[this.active]
                        ?.scrollIntoView({ block: 'nearest' });
                });
            },

            choose() {
                this.$refs.list?.querySelectorAll('[data-wy-cmd-item]')[this.active]?.click();
            },

            // One window-level handler rather than per-element ones. Alpine's .prevent
            // modifier is unconditional, so a window-level x-on:keydown.down.prevent
            // would swallow page scrolling whenever the palette is shut — and handlers
            // bound to the dialog only fire for events that originate inside it, which a
            // key event dispatched at the document does not.
            onKey(event) {
                if (! this.open) {
                    return;
                }

                const handlers = {
                    ArrowDown: () => this.move(1),
                    ArrowUp: () => this.move(-1),
                    Enter: () => this.choose(),
                    Escape: () => this.hide(),
                };

                const handler = handlers[event.key];

                if (! handler) {
                    return;
                }

                event.preventDefault();
                handler();
            },
        }"
        x-on:keydown.window.meta.k.prevent="show()"
        x-on:keydown.window.ctrl.k.prevent="show()"
        x-on:keydown.window="onKey($event)"
        x-on:wyvern-open-palette.window="show()"
    >
        <div
            x-show="open"
            x-cloak
            class="wy-cmd-scrim"
            x-on:click="hide()"
        ></div>

        <div
            x-show="open"
            x-cloak
            class="wy-cmd"
            role="dialog"
            aria-modal="true"
            aria-label="{{ trans('wyvern.palette.label') }}"
            x-trap.noscroll="open"
        >
            <div class="wy-cmd-field">
                <x-filament::icon icon="tabler-search" class="wy-cmd-field-icon" />
                <input
                    x-ref="input"
                    x-model="query"
                    x-on:input="active = 0"
                    type="text"
                    class="wy-cmd-input"
                    placeholder="{{ trans('wyvern.palette.placeholder') }}"
                    autocomplete="off"
                    spellcheck="false"
                >
                <kbd class="wy-cmd-kbd">esc</kbd>
            </div>

            <div class="wy-cmd-list" x-ref="list">
                <template x-for="(item, index) in results" :key="item.group + item.url + index">
                    <a
                        data-wy-cmd-item
                        :href="item.url"
                        wire:navigate
                        class="wy-cmd-item"
                        :class="index === active && 'wy-cmd-item-on'"
                        x-on:mouseenter="active = index"
                        x-on:click="hide()"
                    >
                        <span class="wy-cmd-item-icon" x-html="icons[item.icon] ?? ''"></span>
                        <span class="wy-cmd-item-text">
                            <span class="wy-cmd-item-label" x-text="item.label"></span>
                            <span class="wy-cmd-item-sub" x-show="item.sublabel" x-text="item.sublabel"></span>
                        </span>
                        <span class="wy-cmd-item-group" x-text="item.group"></span>
                    </a>
                </template>

                <p class="wy-cmd-empty" x-show="! results.length">
                    {{ trans('wyvern.palette.empty') }}
                </p>
            </div>
        </div>
    </div>
@endif
