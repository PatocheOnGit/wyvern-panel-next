@php
    use App\Enums\ServerResourceType;
    use Wyvern\ServerCover;

    $icon = $server->icon ?? $server->egg->icon;
@endphp

{{-- The same shape as the loaded card, so nothing jumps once the daemon answers. --}}
<div class="wy-server-card wy-server-card-loading"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    <div class="wy-server-card-cover" style="background: {{ ServerCover::gradient($server) }};">
        @if ($icon)
            <img src="{{ $icon }}" alt="" class="wy-server-card-emblem">
        @else
            <span class="wy-server-card-emblem wy-server-card-emblem-letter"
                  style="color: {{ ServerCover::tint($server) }};">{{ Str::upper(Str::substr($server->name, 0, 2)) }}</span>
        @endif

        <div class="wy-server-card-scrim"></div>

        <div class="wy-server-card-heading">
            <div class="wy-server-card-identity">
                <span class="wy-server-card-egg">{{ $server->egg->name }}</span>
                <h2 class="wy-server-card-name">{{ $server->name }}</h2>
            </div>
            <span class="wy-server-card-state wy-server-card-state-pending">
                <x-filament::loading-indicator class="h-3 w-3" />
                {{ trans('server/dashboard.loading') }}
            </span>
        </div>
    </div>

    <div class="wy-server-card-body">
        <div class="wy-server-card-address">
            <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
        </div>

        @if ($server->description)
            <p class="wy-server-card-description">{{ Str::limit($server->description, 64, preserveWords: true) }}</p>
        @endif

        <div class="wy-server-card-stats">
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.cpu') }}</span>
                <span class="wy-server-card-stat-value wy-server-card-stat-idle">&mdash;</span>
                <span class="wy-server-card-meter"></span>
            </div>
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.memory') }}</span>
                <span class="wy-server-card-stat-value wy-server-card-stat-idle">&mdash;</span>
                <span class="wy-server-card-meter"></span>
            </div>
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.disk') }}</span>
                <span class="wy-server-card-stat-value wy-server-card-stat-idle">&mdash;</span>
                <span class="wy-server-card-meter"></span>
            </div>
        </div>
    </div>
</div>
