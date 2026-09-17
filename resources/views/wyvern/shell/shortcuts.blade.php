@php
    $jumps = \Wyvern\Navigation\Shortcuts::jumps();
@endphp

<div
    x-data="{
        open: false,
        pending: false,
        timer: null,

        jumps: @js($jumps),

        // A shortcut must never fire while someone is writing. Checking the event target
        // rather than a flag keeps it honest for fields that appear after load — a modal,
        // a table filter, the console's own command line.
        //
        // The instanceof guard is not defensive noise: a keydown dispatched on window has
        // window as its target, and window has no closest(), so calling it throws and the
        // whole handler dies silently. A real keyboard always targets an element, which is
        // exactly why this only shows up under test.
        isTyping(event) {
            if (! (event.target instanceof Element)) {
                return false;
            }

            return !! event.target.closest('input, textarea, select, [contenteditable]');
        },

        onKey(event) {
            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (this.open && event.key === 'Escape') {
                this.open = false;

                return;
            }

            if (this.isTyping(event)) {
                return;
            }

            if (event.key === '?') {
                event.preventDefault();
                this.open = ! this.open;

                return;
            }

            if (event.key === 'g') {
                event.preventDefault();
                this.arm();

                return;
            }

            if (! this.pending) {
                return;
            }

            this.disarm();

            const jump = this.jumps[event.key.toLowerCase()];

            if (jump) {
                event.preventDefault();
                this.open = false;
                Livewire.navigate(jump.url);
            }
        },

        // g is a prefix, not a chord, so it has to expire: held open forever, the next
        // letter someone typed anywhere would navigate the page out from under them.
        arm() {
            this.pending = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.pending = false, 1500);
        },

        disarm() {
            this.pending = false;
            clearTimeout(this.timer);
        },
    }"
    x-on:keydown.window="onKey($event)"
    x-on:wyvern-open-shortcuts.window="open = true"
>
    {{-- The armed indicator. Without it, g is an invisible state: you press it, nothing
         happens, and there is no way to tell whether the panel is waiting for you. --}}
    <div x-show="pending" x-cloak class="wy-keyhint">
        <kbd class="wy-cmd-kbd">g</kbd>
        <span>{{ trans('wyvern.keyboard.armed') }}</span>
    </div>

    <div x-show="open" x-cloak class="wy-cmd-scrim" x-on:click="open = false"></div>

    <div
        x-show="open"
        x-cloak
        class="wy-cmd wy-keys"
        role="dialog"
        aria-modal="true"
        aria-label="{{ trans('wyvern.keyboard.title') }}"
        x-trap.noscroll="open"
    >
        <div class="wy-cmd-field">
            <x-filament::icon icon="tabler-keyboard" class="wy-cmd-field-icon" />
            <span class="wy-keys-title">{{ trans('wyvern.keyboard.title') }}</span>
            <kbd class="wy-cmd-kbd">esc</kbd>
        </div>

        <div class="wy-cmd-list">
            <p class="wy-keys-group">{{ trans('wyvern.keyboard.general') }}</p>

            <div class="wy-keys-row">
                <span>{{ trans('wyvern.palette.label') }}</span>
                <span class="wy-keys-keys"><kbd class="wy-cmd-kbd">⌘</kbd><kbd class="wy-cmd-kbd">K</kbd></span>
            </div>

            <div class="wy-keys-row">
                <span>{{ trans('wyvern.keyboard.title') }}</span>
                <span class="wy-keys-keys"><kbd class="wy-cmd-kbd">?</kbd></span>
            </div>

            <div class="wy-keys-row">
                <span>{{ trans('wyvern.keyboard.close') }}</span>
                <span class="wy-keys-keys"><kbd class="wy-cmd-kbd">esc</kbd></span>
            </div>

            @if (filled($jumps))
                <p class="wy-keys-group">{{ trans('wyvern.keyboard.jump') }}</p>

                @foreach ($jumps as $letter => $jump)
                    <div class="wy-keys-row">
                        <span>{{ $jump['label'] }}</span>
                        <span class="wy-keys-keys">
                            <kbd class="wy-cmd-kbd">g</kbd><kbd class="wy-cmd-kbd">{{ $letter }}</kbd>
                        </span>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</div>
