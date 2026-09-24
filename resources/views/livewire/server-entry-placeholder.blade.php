@php
    use Wyvern\ServerCover;

    $icon = $server->icon ?? $server->egg->icon;
    $isPinned = $component->isPinned();
    $pinLabel = trans($isPinned ? 'wyvern.pins.unpin' : 'wyvern.pins.pin');
@endphp

{{-- The same shape as the loaded card, row for row, so nothing jumps once the daemon
     answers. Only what needs the daemon waits: the state and the three figures. --}}
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
            <h2 class="wy-server-card-name">{{ $server->name }}</h2>
            <span class="wy-server-card-egg">{{ $server->egg->name }}</span>
        </span>

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
    </div>

    <div class="wy-server-card-status">
        <span class="wy-server-card-state wy-server-card-state-pending">
            <x-filament::loading-indicator class="h-3 w-3" />{{ trans('server/dashboard.loading') }}
        </span>
        <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
    </div>

    @if ($server->description)
        <p class="wy-server-card-description">{{ Str::limit($server->description, 80, preserveWords: true) }}</p>
    @endif

    <div class="wy-server-card-stats">
        @foreach ([trans('server/dashboard.cpu'), trans('server/dashboard.memory'), trans('server/dashboard.disk')] as $label)
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-head">
                    <span class="wy-server-card-stat-label">{{ $label }}</span>
                    <span class="wy-server-card-stat-value"><span class="wy-skeleton" style="width: 3rem"></span></span>
                </span>
                <span class="wy-server-card-meter wy-skeleton"></span>
            </div>
        @endforeach
    </div>
</div>
