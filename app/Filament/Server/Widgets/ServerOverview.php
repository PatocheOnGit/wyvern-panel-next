<?php

namespace App\Filament\Server\Widgets;

use App\Filament\Server\Components\SmallStatBlock;
use App\Models\Server;
use Carbon\CarbonInterface;
use Filament\Widgets\StatsOverviewWidget;

class ServerOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '1s';

    public ?Server $server = null;

    protected function getStats(): array
    {
        $cpu = $this->cpu();
        $memory = $this->memory();
        $disk = $this->disk();

        return [
            SmallStatBlock::make(trans('server/console.labels.name'), $this->server->name)
                ->copyable(),
            SmallStatBlock::make(trans('server/console.labels.status'), $this->status()),
            SmallStatBlock::make(trans('server/console.labels.address'), $this->server?->allocation->address ?? 'None')
                ->copyable(),
            SmallStatBlock::make(trans('server/console.labels.cpu'), $cpu['value'])
                ->ratio($cpu['ratio']),
            SmallStatBlock::make(trans('server/console.labels.memory'), $memory['value'])
                ->ratio($memory['ratio']),
            SmallStatBlock::make(trans('server/console.labels.disk'), $disk['value'])
                ->ratio($disk['ratio']),
        ];
    }

    private function status(): string
    {
        $status = $this->server->condition->getLabel();
        $uptime = collect(cache()->get("servers.{$this->server->id}.uptime"))->last() ?? 0;

        if ($uptime === 0) {
            return $status;
        }

        $uptime = now()->subMillis($uptime)->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE, short: true, parts: 2);

        return "$status ($uptime)";
    }

    /**
     * An em dash rather than the word "Offline".
     *
     * These three blocks sit next to a Status block that already says the server is off,
     * so repeating it three times spent the only line each block has on something
     * already known — and it read as a measurement, which it is not.
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

        // Disk is the one figure the daemon keeps reporting for a stopped server, so it
        // is not gated on status — but a zero here means it has not reported yet, which
        // is different from a server using no disk.
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
