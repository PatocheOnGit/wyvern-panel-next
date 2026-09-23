<?php

namespace Wyvern\Filament\Widgets;

use App\Enums\ServerState;
use App\Enums\TablerIcon;
use App\Models\Node;
use App\Models\Server;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * What the admin dashboard says before it says anything else.
 *
 * The page it replaces opened on "Welcome to Wyvern!" followed by four prose panels —
 * up to date, information for developers, please donate, need help — and carried no
 * operational information at all. An operator arriving at the panel wants to know
 * whether anything is wrong and how much room is left; both are one query away.
 *
 * Deliberately database-only. Node::statistics() and systemInformation() are cached and
 * would work here, but they reach the daemon, and a dashboard that stalls when a node is
 * unreachable is worse than one that reports allocation instead of live use. Allocation
 * against configured capacity is also the more useful number: it is what decides whether
 * the next server fits.
 */
class FleetOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected static ?int $sort = -10;

    /** @return array<string, int> one per row on a phone, 2×2 on a tablet, a row of four when there is room */
    protected function getColumns(): array
    {
        return ['default' => 1, 'sm' => 2, 'xl' => 4];
    }

    /** @return Stat[] */
    protected function getStats(): array
    {
        return [
            $this->servers(),
            $this->nodes(),
            $this->allocation('memory', trans('wyvern.dashboard.memory')),
            $this->allocation('disk', trans('wyvern.dashboard.disk')),
        ];
    }

    private function servers(): Stat
    {
        $total = Server::count();
        $suspended = Server::where('status', ServerState::Suspended)->count();
        $failed = Server::whereIn('status', [ServerState::InstallFailed, ServerState::ReinstallFailed])->count();
        $wrong = $suspended + $failed;

        return Stat::make(trans('wyvern.dashboard.servers'), (string) $total)
            ->description($wrong === 0
                ? trans('wyvern.dashboard.servers_ok')
                : trans('wyvern.dashboard.servers_attention', ['suspended' => $suspended, 'failed' => $failed]))
            ->descriptionIcon($wrong === 0 ? TablerIcon::CircleCheck : TablerIcon::AlertTriangle)
            ->color($wrong === 0 ? 'success' : 'warning');
    }

    private function nodes(): Stat
    {
        $total = Node::count();
        $maintenance = Node::where('maintenance_mode', true)->count();

        return Stat::make(trans('wyvern.dashboard.nodes'), (string) $total)
            ->description($maintenance === 0
                ? trans('wyvern.dashboard.nodes_ok')
                : trans('wyvern.dashboard.nodes_maintenance', ['count' => $maintenance]))
            ->descriptionIcon($maintenance === 0 ? TablerIcon::CircleCheck : TablerIcon::Tool)
            ->color($maintenance === 0 ? 'success' : 'warning');
    }

    /**
     * Allocation against capacity, for a column both models store in MiB.
     *
     * A node with the column set to 0 is explicitly unlimited, so it contributes nothing
     * to a ceiling — and if every node is unlimited there is no ratio to report rather
     * than a misleading 100%.
     */
    private function allocation(string $column, string $label): Stat
    {
        $used = (int) Server::sum($column);
        $capacity = (int) Node::where($column, '>', 0)->sum($column);

        $mib = 1024 * 1024;

        if ($capacity <= 0) {
            return Stat::make($label, Number::fileSize($used * $mib, maxPrecision: 1))
                ->description(trans('wyvern.dashboard.no_ceiling'))
                ->descriptionIcon(TablerIcon::Infinity)
                ->color('gray');
        }

        $ratio = $used / $capacity;

        return Stat::make($label, Number::fileSize($used * $mib, maxPrecision: 1))
            ->description(trans('wyvern.dashboard.of_capacity', [
                'percent' => round($ratio * 100),
                'capacity' => Number::fileSize($capacity * $mib, maxPrecision: 1),
            ]))
            ->descriptionIcon(TablerIcon::ChartPie)
            ->color(match (true) {
                $ratio >= 0.9 => 'danger',
                $ratio >= 0.7 => 'warning',
                default => 'success',
            });
    }
}
