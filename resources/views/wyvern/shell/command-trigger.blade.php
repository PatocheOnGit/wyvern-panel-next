{{-- The discoverable half of the shortcut. A keyboard-only feature is a feature most
     people never learn they have; the keycap teaches it. --}}
<button
    type="button"
    x-data
    class="wy-cmd-trigger"
    x-on:click="$dispatch('wyvern-open-palette')"
    aria-label="{{ trans('wyvern.palette.label') }}"
>
    <x-filament::icon icon="tabler-search" />
    <span class="wy-cmd-trigger-label">{{ trans('wyvern.palette.trigger') }}</span>
    <kbd class="wy-cmd-kbd">{{ trans('wyvern.palette.shortcut') }}</kbd>
</button>
