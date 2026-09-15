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

    $meter = function (int|float $current, int|float $max) use ($warningPercent, $dangerPercent) {
        $ratio = $max > 0 ? $current / $max : 0;
        $status = $ratio >= $dangerPercent ? 'danger' : ($ratio >= $warningPercent ? 'warning' : 'success');

        return [
            'percent' => max(0, min(100, $ratio * 100)),
            'colour' => match ($status) {
                'danger' => 'var(--danger-400)',
                'warning' => 'var(--warning-400)',
                default => 'var(--primary-400)',
            },
        ];
    };

    $cpu = $meter($cpuCurrent, $cpuMax);
    $mem = $meter($memCurrent, $memMax);
    $disk = $meter($diskCurrent, $diskMax);
@endphp

<div wire:poll.15s
     class="wy-server-card"
     x-on:click="{{ $component->redirectUrl() }}"
     x-on:auxclick.prevent="if ($event.button === 1) {{ $component->redirectUrl(true) }}">

    {{-- cover --}}
    <div class="wy-server-card-cover" style="background: {{ ServerCover::gradient($server) }};">
        @if ($icon)
            <img src="{{ $icon }}" alt="" class="wy-server-card-emblem">
        @else
            <span class="wy-server-card-emblem wy-server-card-emblem-letter"
                  style="color: {{ ServerCover::tint($server) }};">{{ Str::upper(Str::substr($server->name, 0, 2)) }}</span>
        @endif

        <div class="wy-server-card-scrim"></div>

        <div class="wy-server-card-heading">
            <span class="wy-server-card-egg">{{ $server->egg->name }}</span>
            <h2 class="wy-server-card-name">{{ $server->name }}</h2>
        </div>
    </div>

    {{-- body --}}
    <div class="wy-server-card-body">
        <div class="wy-server-card-address">
            <span class="wy-server-card-state fi-color fi-color-{{ $server->condition->getColor() }}">
                <i class="wy-server-card-dot"></i>{{ $server->condition->getLabel() }}
            </span>
            <span class="wy-server-card-host">{{ $server->allocation?->address ?? trans('server/dashboard.none') }}</span>
            @if ($uptime > 0)
                <span class="wy-server-card-uptime">{{ $server->formatResource(ServerResourceType::Uptime) }}</span>
            @endif
        </div>

        @if ($server->description)
            <p class="wy-server-card-description">{{ Str::limit($server->description, 64, preserveWords: true) }}</p>
        @endif

        <div class="wy-server-card-stats">
            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.cpu') }}</span>
                <span class="wy-server-card-stat-value">{{ $server->formatResource(ServerResourceType::CPU, 0) }}</span>
                <span class="wy-server-card-meter"><i style="width: {{ $cpu['percent'] }}%; background: {{ $cpu['colour'] }};"></i></span>
            </div>

            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.memory') }}</span>
                <span class="wy-server-card-stat-value">{{ $server->formatResource(ServerResourceType::Memory) }}</span>
                <span class="wy-server-card-meter"><i style="width: {{ $mem['percent'] }}%; background: {{ $mem['colour'] }};"></i></span>
            </div>

            <div class="wy-server-card-stat">
                <span class="wy-server-card-stat-label">{{ trans('server/dashboard.disk') }}</span>
                <span class="wy-server-card-stat-value">{{ $server->formatResource(ServerResourceType::Disk) }}</span>
                <span class="wy-server-card-meter"><i style="width: {{ $disk['percent'] }}%; background: {{ $disk['colour'] }};"></i></span>
            </div>
        </div>

        @if ($actiongroup->isVisible())
            <div class="wy-server-card-actions" x-on:click.stop>
                {{ $actiongroup }}
            </div>
        @endif
    </div>
</div>
