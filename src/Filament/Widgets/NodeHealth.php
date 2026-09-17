<?php

namespace Wyvern\Filament\Widgets;

use App\Filament\Admin\Resources\Nodes\Pages\EditNode;
use App\Filament\Components\Tables\Columns\NodeClientHealthColumn;
use App\Filament\Components\Tables\Columns\NodeHealthColumn;
use App\Models\Node;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Number;

/**
 * One row per node, answering the question the dashboard exists for.
 *
 * The two health columns are Pelican's own — NodeHealthColumn asks the daemon whether it
 * is alive, NodeClientHealthColumn whether the browser can reach it, which are different
 * failures with different fixes. Reusing them means the dashboard and the node list
 * cannot disagree about whether a node is up.
 *
 * Everything else here is a database fact. Allocation against the node's configured
 * ceiling is what decides whether the next server fits, and unlike live utilisation it
 * cannot be made unavailable by the thing it is reporting on.
 */
class NodeHealth extends TableWidget
{
    protected static ?int $sort = -5;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Node::query()->withCount('servers'))
            ->heading(trans('wyvern.dashboard.node_health'))
            ->paginated(false)
            ->defaultSort('name')
            ->columns([
                NodeHealthColumn::make('health'),
                NodeClientHealthColumn::make('reachable'),
                TextColumn::make('name')
                    ->label(trans('admin/node.table.name'))
                    ->url(fn (Node $node) => EditNode::getUrl(['record' => $node], panel: 'admin')),
                TextColumn::make('fqdn')
                    ->label(trans('admin/node.table.address'))
                    ->visibleFrom('md'),
                TextColumn::make('servers_count')
                    ->label(trans('admin/node.table.servers'))
                    ->visibleFrom('sm'),
                TextColumn::make('memory')
                    ->label(trans('wyvern.dashboard.memory'))
                    ->state(fn (Node $node) => $this->allocation($node, 'memory'))
                    ->visibleFrom('lg'),
                TextColumn::make('disk')
                    ->label(trans('wyvern.dashboard.disk'))
                    ->state(fn (Node $node) => $this->allocation($node, 'disk'))
                    ->visibleFrom('lg'),
            ])
            ->emptyStateHeading(trans('admin/node.no_nodes'));
    }

    /** Both columns are stored in MiB, on the node and on its servers alike. */
    private function allocation(Node $node, string $column): string
    {
        $mib = 1024 * 1024;
        $used = (int) $node->servers()->sum($column);
        $capacity = (int) $node->{$column};

        if ($capacity <= 0) {
            return Number::fileSize($used * $mib, maxPrecision: 1) . ' / ∞';
        }

        return Number::fileSize($used * $mib, maxPrecision: 1)
            . ' / ' . Number::fileSize($capacity * $mib, maxPrecision: 1);
    }
}
