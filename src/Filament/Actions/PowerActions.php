<?php

namespace Wyvern\Filament\Actions;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
use Illuminate\Http\Client\ConnectionException;

/**
 * Start, restart, stop and kill, reachable from every page of a server.
 *
 * They used to exist only on the console, so restarting a server while looking at its
 * files meant going to the console and coming back. Nothing about the console makes it
 * the right place to own them; it is just where they happened to be declared.
 *
 * This is a second implementation rather than a reuse of
 * ListServers::getPowerActionGroup(), for a structural reason: that group does not act,
 * it dispatches a 'powerAction' Livewire event, and the only listener for it lives on the
 * app panel's server list. On any other page those buttons would appear, be clickable,
 * and do nothing. These call the daemon in their own closure, so they work wherever they
 * are registered.
 */
final class PowerActions
{
    /**
     * One menu rather than four buttons.
     *
     * The console keeps its explicit Start / Restart / Stop, because that is the page you
     * sit on while a server comes up. Everywhere else this is an occasional need beside a
     * page that has its own controls, and a dropdown does not compete with them.
     */
    public static function group(): ActionGroup
    {
        return ActionGroup::make([
            self::action('start', TablerIcon::PlayerPlayFilled, 'primary', SubuserPermission::ControlStart),
            self::action('restart', TablerIcon::Reload, 'gray', SubuserPermission::ControlRestart),
            self::action('stop', TablerIcon::PlayerStopFilled, 'danger', SubuserPermission::ControlStop),
            self::action('kill', TablerIcon::AlertSquare, 'danger', SubuserPermission::ControlStop),
        ])
            ->label(trans('server/dashboard.power_actions'))
            ->icon(TablerIcon::Power)
            ->color('primary')
            ->button()
            ->hidden(fn () => self::server()?->isInConflictState() ?? true)
            ->iconSize(IconSize::Large);
    }

    private static function action(string $power, TablerIcon $icon, string $color, SubuserPermission $permission): Action
    {
        return Action::make($power)
            ->label(trans("server/console.power_actions.$power"))
            ->icon($icon)
            ->color($color)
            // Each power is offered only when the daemon says the server can take it, which
            // is what keeps "start" off a running server.
            ->visible(function () use ($power, $permission) {
                $server = self::server();

                if ($server === null || !user()?->can($permission, $server)) {
                    return false;
                }

                $status = $server->retrieveStatus();

                return match ($power) {
                    'start' => $status->isStartable(),
                    'restart' => $status->isRestartable(),
                    'stop' => $status->isStoppable() && !$status->isKillable(),
                    'kill' => $status->isKillable(),
                    default => false,
                };
            })
            // Kill drops the container without letting the server save. That is closer to
            // pulling a plug than to stopping a service, so it asks first.
            ->requiresConfirmation($power === 'kill')
            ->action(fn () => self::send($power));
    }

    private static function send(string $power): void
    {
        $server = self::server();

        if ($server === null) {
            return;
        }

        try {
            app(DaemonServerRepository::class)->setServer($server)->power($power);

            // The status is cached, and without dropping it the page keeps reporting the
            // state the server was in before the button was pressed.
            cache()->forget("servers.$server->uuid.status");

            Notification::make()
                ->title(trans('server/dashboard.power_actions'))
                ->body(trans('server/dashboard.power_action_sent', ['action' => $power, 'name' => $server->name]))
                ->success()
                ->send();
        } catch (ConnectionException) {
            Notification::make()
                ->title(trans('exceptions.node.error_connecting', ['node' => $server->node->name]))
                ->danger()
                ->send();
        }
    }

    /**
     * The server these act on is the panel's tenant, never a table record: they are page
     * header actions, so there is no record to resolve from.
     */
    private static function server(): ?Server
    {
        $tenant = Filament::getTenant();

        return $tenant instanceof Server ? $tenant : null;
    }
}
