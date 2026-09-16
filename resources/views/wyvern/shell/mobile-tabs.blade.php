@php
    $tabs = \Wyvern\Navigation\MobileTabs::for(\Filament\Facades\Filament::getCurrentPanel()?->getId() ?? '');
@endphp

@if (filled($tabs))
    {{-- x-data is load-bearing, not decoration: the bar is injected on BODY_END,
         outside Filament's own Alpine tree, and Alpine only binds x-on inside an
         x-data scope. Without it the More button is an inert button. --}}
    <nav x-data class="wy-tabbar" aria-label="{{ trans('wyvern.navigation.mobile_label') }}">
        @foreach ($tabs as $tab)
            <a
                href="{{ $tab['url'] }}"
                wire:navigate
                class="wy-tabbar-item @if ($tab['active']) wy-tabbar-item-on @endif"
                @if ($tab['active']) aria-current="page" @endif
            >
                <x-filament::icon :icon="$tab['icon']" class="wy-tabbar-icon" />
                <span>{{ $tab['label'] }}</span>
            </a>
        @endforeach

        {{-- Opens Filament's own drawer rather than a second navigation of ours, so the
             full tree stays in one place and one mental model. --}}
        <button type="button" class="wy-tabbar-item" x-on:click="$store.sidebar.open()">
            <x-filament::icon icon="tabler-dots" class="wy-tabbar-icon" />
            <span>{{ trans('wyvern.navigation.more') }}</span>
        </button>
    </nav>
@endif
