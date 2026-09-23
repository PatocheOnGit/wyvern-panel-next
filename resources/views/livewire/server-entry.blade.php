@php
    use App\Enums\ServerResourceType;
    use App\Filament\App\Resources\Servers\Pages\ListServers;
    use App\Filament\Components\Tables\Columns\ServerEntryColumn;
    use Wyvern\ServerCover;

    $actiongroup = ListServers::getPowerActionGroup()->record($server);
    $icon = $server->icon ?? $server->egg->icon;

    $serverEntryColumn = $column ?? ServerEntryColumn::make('server_entry');
    $nodeStatistics = $server->node->statistics();
    $nodeSystemInfo = $server->node->systemInformation();

    $warningPercent = $serverEntryColumn->getWarningThresholdPercent() ?? 0.7;
    $dangerPercent = $serverEntryColumn->getDangerThresholdPercent() ?? 0.9;

    $cpuCurrent = ServerResourceType::CPU->getResourceAmount($server);
    $cpuMax = ServerResourceType::CPULimit->getResourceAmount($server) === 0
        ? (($nodeSystemInfo['cpu_count'] ?? 0) * 100)
        : ServerResourceType::CPULimit->getResourceAmount($server);

    $memCurrent = ServerResourceType::Memory->getResourceAmount($server);
    $memMax = ServerResourceType::MemoryLimit->getResourceAmount($server) === 0
        ? ($nodeStatistics['memory_total'] ?? 0)
        : ServerResourceType::MemoryLimit->getResourceAmount($server);

    $uptime = ServerResourceType::Uptime->getResourceAmount($server);
    $diskCurrent = ServerResourceType::Disk->getResourceAmount($server);
    $diskMax = ServerResourceType::DiskLimit->getResourceAmount($server) === 0
        ? ($nodeStatistics['disk_total'] ?? 0)
        : ServerResourceType::DiskLimit->getResourceAmount($server);

    // A meter is only honest when there is a ceiling to measure against. Where a limit is
    // unset the card falls back to the node's own capacity above; where even that is
    // unknown the bar is suppressed rather than drawn at zero, which would read as idle.
    $meter = function (int|float $current, int|float $max) use ($warningPercent, $dangerPercent) {
        if ($max <= 0) {
            return ['percent' => null, 'colour' => null];
        }

        $ratio = $current / $max;

        return [
            'percent' => max(0, min(100, $ratio * 100)),
            'colour' => match (true) {
                $ratio >= $dangerPercent => 'var(--wy-offline)',
                $ratio >= $warningPercent => 'var(--wy-transition)',
                default => 'var(--wy-accent)',
            },
        ];
    };

    $stats = [
        ['label' => trans('server/dashboard.cpu'), 'value' => $server->formatResource(ServerResourceType::CPU, 0), 'meter' => $meter($cpuCurrent, $cpuMax)],
        ['label' => trans('server/dashboard.memory'), 'value' => $server->formatResource(ServerResourceType::Memory), 'meter' => $meter($memCurrent, $memMax)],
        ['label' => trans('server/dashboard.disk'), 'value' => $server->formatResource(ServerResourceType::Disk), 'meter' => $meter($diskCurrent, $diskMax)],
    ];
@endphp

<div wire:poll.15s
     class="wy-server-card"
     style="--wy-server-hue: {{ ServerCover::stripe($server) }}; --wy-server-plate: {{ ServerCover::plate($server) }};"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    {{-- The head answers the two questions a card is scanned for: which server, and is
         it up. The game identity is the edge and the emblem, not a picture behind the
         title — it used to take 40% of the card and crop its own icon. --}}
    <div class="wy-server-card-head">
        <span class="wy-server-card-emblem">
            @if ($icon)
                <img src="{{ $icon }}" alt="">
            @else
                <span class="wy-server-card-initials"
                      style="color: {{ ServerCover::tint($server) }};">{{ Str::upper(Str::substr($server->name, 0, 2)) }}</span>
            @endif
        </span>

        @php
            $summary = app(\Wyvern\Servers\GameSummary::class);
            $software = $summary->software($server);
            $players = $summary->players($server);
        @endphp

        <span class="wy-server-card-heading">
            <span class="wy-server-card-egg">{{ $software ?? $server->egg->name }}</span>
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

        <span class="wy-server-card-state fi-color fi-color-{{ $server->condition->getColor() }}">
            <i class="wy-server-card-dot"></i>{{ $server->condition->getLabel() }}
        </span>
    </div>

    <div class="wy-server-card-body">
        <div class="wy-server-card-meta">
            <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
            @if ($uptime > 0)
                <span class="wy-server-card-uptime">{{ $server->formatResource(ServerResourceType::Uptime) }}</span>
            @endif
            @if ($players !== null)
                <span class="wy-server-card-players">
                    <x-filament::icon icon="tabler-users" class="h-3.5 w-3.5" />
                    {{ trans('wyvern.cards.players', ['online' => $players['online'], 'max' => $players['max']]) }}
                </span>
            @endif
        </div>

        @if ($server->description)
            <p class="wy-server-card-description">{{ Str::limit($server->description, 64, preserveWords: true) }}</p>
        @endif

        <div class="wy-server-card-stats">
            @foreach ($stats as $stat)
                <div class="wy-server-card-stat">
                    <span class="wy-server-card-stat-label">{{ $stat['label'] }}</span>
                    <span class="wy-server-card-stat-value">{{ $stat['value'] }}</span>
                    @if ($stat['meter']['percent'] !== null)
                        <span class="wy-server-card-meter"><i style="width: {{ $stat['meter']['percent'] }}%; background: {{ $stat['meter']['colour'] }};"></i></span>
                    @else
                        <span class="wy-server-card-meter wy-server-card-meter-unmetered"></span>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($actiongroup->isVisible())
            <div class="wy-server-card-actions" x-on:click.stop>
                {{ $actiongroup }}
            </div>
        @endif
    </div>
</div>
