<?php

namespace App\Filament\Server\Widgets;

use App\Models\Server;
use Carbon\CarbonInterface;
use Filament\Widgets\Widget;
use Wyvern\Servers\GameSummary;

/** Wyvern: one card above the console, a status line and three meters, instead of eight blocks. */
class ServerOverview extends Widget
{
    protected string $view = 'filament.server.widgets.server-overview';

    protected int|string|array $columnSpan = 'full';

    public ?Server $server = null;

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $summary = app(GameSummary::class);
        $software = $summary->software($this->server);

        return [
            'condition' => $this->server->condition,
            'uptime' => $this->uptime(),
            'address' => $this->server->allocation?->address,
            'software' => $software,
            'players' => $software !== null ? $summary->players($this->server) : null,
            'meters' => [
                trans('server/console.labels.cpu') => $this->cpu(),
                trans('server/console.labels.memory') => $this->memory(),
                trans('server/console.labels.disk') => $this->disk(),
            ],
        ];
    }

    private function uptime(): ?string
    {
        $uptime = collect(cache()->get("servers.{$this->server->id}.uptime"))->last() ?? 0;

        return $uptime === 0 ? null : now()->subMillis($uptime)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 2);
    }

    /**
     * An em dash rather than the word "Offline": the status line already says so, and a
     * dash does not read as a measurement.
     *
     * @return array{value: string, ratio: float|null}
     */
    private function idle(): array
    {
        return ['value' => '—', 'ratio' => null];
    }

    /** @return array{value: string, ratio: float|null} */
    private function cpu(): array
    {
        if ($this->server->retrieveStatus()->isOffline()) {
            return $this->idle();
        }

        $used = collect(cache()->get("servers.{$this->server->id}.cpu_absolute"))->last(default: 0);
        $limit = $this->server->cpu;

        return [
            'value' => format_number($used, maxPrecision: 2) . ' %'
                . ($limit > 0 ? ' / ' . format_number($limit) . ' %' : ' / ∞'),
            'ratio' => $limit > 0 ? $used / $limit : null,
        ];
    }

    /** @return array{value: string, ratio: float|null} */
    private function memory(): array
    {
        if ($this->server->retrieveStatus()->isOffline()) {
            return $this->idle();
        }

        $used = collect(cache()->get("servers.{$this->server->id}.memory_bytes"))->last(default: 0);
        $total = $this->server->memory * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);

        return [
            'value' => convert_bytes_to_readable($used)
                . ($this->server->memory > 0 ? ' / ' . convert_bytes_to_readable($total) : ' / ∞'),
            'ratio' => $total > 0 ? $used / $total : null,
        ];
    }

    /** @return array{value: string, ratio: float|null} */
    private function disk(): array
    {
        $used = collect(cache()->get("servers.{$this->server->id}.disk_bytes"))->last(default: 0);

        // The daemon keeps reporting disk for a stopped server; zero means no report yet.
        if ($used === 0) {
            return ['value' => trans('server/console.labels.unavailable'), 'ratio' => null];
        }

        $total = $this->server->disk * (config('panel.use_binary_prefix') ? 1024 * 1024 : 1000 * 1000);

        return [
            'value' => convert_bytes_to_readable($used)
                . ($this->server->disk > 0 ? ' / ' . convert_bytes_to_readable($total) : ' / ∞'),
            'ratio' => $total > 0 ? $used / $total : null,
        ];
    }
}
