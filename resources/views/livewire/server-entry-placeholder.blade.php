@php
    use Wyvern\ServerCover;

    $icon = $server->icon ?? $server->egg->icon;
@endphp

{{-- The same shape as the loaded card, down to the row count, so nothing jumps once the
     daemon answers. Everything here is known without asking it: the name, the game, the
     address. Only the state and the three figures have to wait. --}}
<div class="wy-server-card wy-server-card-loading"
     style="--wy-server-hue: {{ ServerCover::stripe($server) }}; --wy-server-plate: {{ ServerCover::plate($server) }};"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    <div class="wy-server-card-head">
        <span class="wy-server-card-emblem">
            @if ($icon)
                <img src="{{ $icon }}" alt="">
            @else
                <span class="wy-server-card-initials"
                      style="color: {{ ServerCover::tint($server) }};">{{ Str::upper(Str::substr($server->name, 0, 2)) }}</span>
            @endif
        </span>

        <span class="wy-server-card-heading">
            <span class="wy-server-card-egg">{{ $server->egg->name }}</span>
            <h2 class="wy-server-card-name">{{ $server->name }}</h2>
        </span>

        {{-- Before the state chip so the two never swap places: the pin is a control and
             the chip is a readout, and a control that moves is a control you misclick.

             $component is read into a local first because Blade rebinds $component inside
             a component tag — calling $component->isPinned() in the icon's attribute
             resolves against the icon component, not this one, and throws. --}}
        @php
            $isPinned = $component->isPinned();
            $pinLabel = trans($isPinned ? 'wyvern.pins.unpin' : 'wyvern.pins.pin');
        @endphp

        <button
            type="button"
            class="wy-server-card-pin @if ($isPinned) wy-server-card-pin-on @endif"
            wire:click.stop="togglePin"
            x-on:click.stop
            aria-pressed="{{ $isPinned ? 'true' : 'false' }}"
            aria-label="{{ $pinLabel }}"
            title="{{ $pinLabel }}"
        >
            <x-filament::icon :icon="$isPinned ? 'tabler-pinned-filled' : 'tabler-pin'" />
        </button>

        <span class="wy-server-card-state wy-server-card-state-pending">
            <x-filament::loading-indicator class="h-3 w-3" />{{ trans('server/dashboard.loading') }}
        </span>
    </div>

    <div class="wy-server-card-body">
        <div class="wy-server-card-meta">
            <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
        </div>

        @if ($server->description)
            <p class="wy-server-card-description">{{ Str::limit($server->description, 64, preserveWords: true) }}</p>
        @endif

        <div class="wy-server-card-stats">
            @foreach ([trans('server/dashboard.cpu'), trans('server/dashboard.memory'), trans('server/dashboard.disk')] as $label)
                <div class="wy-server-card-stat">
                    <span class="wy-server-card-stat-label">{{ $label }}</span>
                    {{-- A bar where the figure will be, not an em dash. The dash was
                         indistinguishable from the one a running server shows for an
                         unmetered resource, so a card that was still loading looked like a
                         card with nothing to measure. --}}
                    <span class="wy-server-card-stat-value"><span class="wy-skeleton" style="width: 60%"></span></span>
                    <span class="wy-server-card-meter wy-skeleton"></span>
                </div>
            @endforeach
        </div>
    </div>
</div>
