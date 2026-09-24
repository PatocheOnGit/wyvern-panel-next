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

    // A stopped server uses no CPU or memory: a dash, not a zero that reads as idle.
    $idle = $server->retrieveStatus()->isOffline();
    $none = ['percent' => null, 'colour' => null];

    $stats = [
        ['label' => trans('server/dashboard.cpu'), 'value' => $idle ? '—' : $server->formatResource(ServerResourceType::CPU, 0), 'meter' => $idle ? $none : $meter($cpuCurrent, $cpuMax)],
        ['label' => trans('server/dashboard.memory'), 'value' => $idle ? '—' : $server->formatResource(ServerResourceType::Memory), 'meter' => $idle ? $none : $meter($memCurrent, $memMax)],
        ['label' => trans('server/dashboard.disk'), 'value' => $server->formatResource(ServerResourceType::Disk), 'meter' => $meter($diskCurrent, $diskMax)],
    ];
@endphp

@php
    $summary = app(\Wyvern\Servers\GameSummary::class);
    $software = $summary->software($server);
    $players = $summary->players($server);
    $emblem = $icon ? ['logo' => $icon] : $summary->emblem($server);

    // $component is read into locals first: Blade rebinds $component inside component tags.
    $isPinned = $component->isPinned();
    $pinLabel = trans($isPinned ? 'wyvern.pins.unpin' : 'wyvern.pins.pin');
@endphp

<div wire:poll.15s
     class="wy-server-card"
     style="--wy-server-hue: {{ ServerCover::stripe($server) }}; --wy-server-plate: {{ ServerCover::plate($server) }};"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    {{-- Which server, what it runs, and the two controls, on one line. --}}
    <div class="wy-server-card-head">
        <span class="wy-server-card-emblem">
            @if (isset($emblem['logo']))
                <img src="{{ $emblem['logo'] }}" alt="">
            @elseif (isset($emblem['icon']))
                <x-filament::icon :icon="$emblem['icon']" class="wy-server-card-emblem-icon" style="color: {{ ServerCover::tint($server) }};" />
            @else
                <span class="wy-server-card-initials"
                      style="color: {{ ServerCover::tint($server) }};">{{ Str::upper(Str::substr($server->name, 0, 2)) }}</span>
            @endif
        </span>

        <span class="wy-server-card-heading">
            <h2 class="wy-server-card-name">{{ $server->name }}</h2>
            <span class="wy-server-card-egg">{{ $software ?? $server->egg->name }}</span>
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

        @if ($actiongroup->isVisible())
            <span class="wy-server-card-actions" x-on:click.stop>
                {{ $actiongroup }}
            </span>
        @endif
    </div>

    {{-- Is it up, where, and who is on it. --}}
    <div class="wy-server-card-status">
        <span class="wy-server-card-state fi-color fi-color-{{ $server->condition->getColor() }}">
            <i class="wy-server-card-dot"></i>{{ $server->condition->getLabel() }}
            @if ($uptime > 0)
                <span class="wy-server-card-uptime">{{ $server->formatResource(ServerResourceType::Uptime) }}</span>
            @endif
        </span>
        <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
        @if ($players !== null)
            <span class="wy-server-card-players">
                <x-filament::icon icon="tabler-users" class="h-3.5 w-3.5" />
                {{ $players['online'] }}/{{ $players['max'] }}
            </span>
        @endif
    </div>

    @if ($server->description)
        <p class="wy-server-card-description">{{ Str::limit($server->description, 80, preserveWords: true) }}</p>
    @endif

    <div class="wy-server-card-stats">
        @foreach ($stats as $stat)
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-head">
                    <span class="wy-server-card-stat-label">{{ $stat['label'] }}</span>
                    <span class="wy-server-card-stat-value">{{ $stat['value'] }}</span>
                </span>
                @if ($stat['meter']['percent'] !== null)
                    <span class="wy-server-card-meter"><i style="width: {{ $stat['meter']['percent'] }}%; background: {{ $stat['meter']['colour'] }};"></i></span>
                @else
                    <span class="wy-server-card-meter wy-server-card-meter-unmetered"></span>
                @endif
            </div>
        @endforeach
    </div>
</div>
