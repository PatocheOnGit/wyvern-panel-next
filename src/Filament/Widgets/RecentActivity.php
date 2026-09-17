<?php

namespace Wyvern\Filament\Widgets;

use App\Models\ActivityLog;
use App\Models\User;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The last thing that happened, on the page an operator opens first.
 *
 * The panel already records this well — and the activity log is the only way to answer
 * "did someone do that, or did it happen to us", which cost real time during setup when
 * power events appeared that no script had sent. Having it on the dashboard is the
 * difference between that question taking two minutes and taking twenty.
 *
 * Note what it cannot tell you: server and egg deletion are not among the events Pelican
 * writes, so a server vanishing leaves no row here. That is a gap in the log, not in this
 * widget, and worth closing separately.
 */
class RecentActivity extends TableWidget
{
    protected static ?int $sort = -4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // Ordered and capped in the query, not on the table: Filament\Tables\Table
            // has no limit(), and with paginated(false) it calls get() on whatever this
            // returns, so the query is the right place for both.
            ->query(fn () => ActivityLog::query()->with('actor')->latest('timestamp')->limit(8))
            ->heading(trans('wyvern.dashboard.recent_activity'))
            ->paginated(false)
            ->columns([
                TextColumn::make('event')
                    ->label(trans('server/activity.event'))
                    ->icon(fn (ActivityLog $log) => $log->getIcon())
                    // The labels carry <b> around the thing acted on, so without this the
                    // tags print literally. Safe to render: ActivityLog::wrapProperties()
                    // puts every interpolated value through stripTags(), so the only
                    // markup reaching here comes from lang/*/activity.php, and the file
                    // names and paths that a user controls arrive as plain text.
                    ->html()
                    ->formatStateUsing(fn (ActivityLog $log) => $log->getLabel()),
                TextColumn::make('actor')
                    ->label(trans('server/activity.user'))
                    // An actor-less entry is the panel acting on its own — a daemon
                    // report, a scheduled task — and saying so is more useful than a
                    // blank cell, which reads as missing data.
                    ->state(fn (ActivityLog $log) => $log->actor instanceof User
                        ? $log->actor->username
                        : ($log->actor_id === null ? trans('server/activity.system') : trans('server/activity.deleted_user')))
                    ->tooltip(fn (ActivityLog $log) => user()?->can('seeIps activityLog') ? $log->ip : null),
                TextColumn::make('timestamp')
                    ->label(trans('server/activity.timestamp'))
                    ->since()
                    ->tooltip(fn (ActivityLog $log) => (string) $log->timestamp),
            ])
            ->emptyStateHeading(trans('wyvern.dashboard.no_activity'));
    }
}
