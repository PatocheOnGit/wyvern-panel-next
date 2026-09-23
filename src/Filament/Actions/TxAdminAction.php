<?php

namespace Wyvern\Filament\Actions;

use App\Enums\SubuserPermission;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\HtmlString;
use Wyvern\FiveM\FiveMServer;
use Wyvern\Minecraft\Reinstaller;

/** Open txAdmin, with the first-run PIN when the console still shows it. */
final class TxAdminAction
{
    public static function make(): Action
    {
        return Action::make('txadmin')
            ->label('txAdmin')
            ->icon('tabler-shield-cog')
            ->color('gray')
            ->button()
            ->visible(fn () => self::fivem()?->usesTxAdmin() ?? false)
            ->modalHeading('txAdmin')
            ->modalDescription(fn () => self::description())
            ->schema(fn () => self::fivem()?->txAdminReachable() ? [] : [
                Select::make('allocation')
                    ->label(trans('wyvern.fivem.txadmin.allocation'))
                    ->options(fn () => self::server()->allocations()
                        ->where('id', '!=', self::server()->allocation_id)
                        ->pluck('port', 'port')
                        ->all())
                    ->helperText(trans('wyvern.fivem.txadmin.allocation_help'))
                    ->required(),
            ])
            // Reachable: the only thing to do is open it, in a tab of its own.
            ->modalSubmitAction(fn (Action $submit) => self::fivem()?->txAdminReachable() ? false : $submit->label(trans('wyvern.fivem.txadmin.use_port')))
            ->extraModalFooterActions(fn () => self::fivem()?->txAdminReachable() ? [
                Action::make('open_txadmin')
                    ->label(trans('wyvern.fivem.txadmin.open'))
                    ->icon('tabler-external-link')
                    ->button()
                    ->color('primary')
                    ->url(self::url(self::fivem()), shouldOpenInNewTab: true),
            ] : [])
            ->action(function (array $data, Reinstaller $reinstaller) {
                $fivem = self::fivem();

                if ($fivem === null || $fivem->txAdminReachable()) {
                    return;
                }

                abort_unless(user()?->can(SubuserPermission::StartupUpdate, $fivem->server), 403);

                $values = $reinstaller->validate($fivem->server, ['TXHOST_TXA_PORT' => $data['allocation']], ['TXHOST_TXA_PORT']);
                $reinstaller->apply($fivem->server, $values, null, false);

                Notification::make()
                    ->title(trans('wyvern.fivem.txadmin.port_set', ['port' => $data['allocation']]))
                    ->body(trans('wyvern.properties.notifications.restart'))
                    ->success()
                    ->send();
            });
    }

    private static function description(): HtmlString
    {
        $fivem = self::fivem();

        if ($fivem === null) {
            return new HtmlString('');
        }

        if (!$fivem->txAdminReachable()) {
            return new HtmlString(e(trans('wyvern.fivem.txadmin.unreachable', ['port' => $fivem->txAdminPort()])));
        }

        $pin = self::pin($fivem->server);
        $text = e(trans('wyvern.fivem.txadmin.address', ['url' => self::url($fivem)]));

        return new HtmlString($pin === null
            ? $text
            : $text . '<br><br>' . e(trans('wyvern.fivem.txadmin.pin')) . ' <strong class="wy-pin">' . e($pin) . '</strong>');
    }

    /** txAdmin prints its PIN once, on the line after "Use the PIN below to register". */
    private static function pin(Server $server): ?string
    {
        try {
            $lines = Http::daemon($server->node)->get("/api/servers/{$server->uuid}/logs", ['size' => 100])->json('data') ?? [];
        } catch (\Throwable) {
            return null;
        }

        $lines = array_map(fn ($l) => preg_replace('/\x1b\[[0-9;]*m/', '', (string) $l), (array) $lines);

        foreach ($lines as $i => $line) {
            if (str_contains($line, 'Use the PIN below to register') && preg_match('/\b(\d{4})\b/', $lines[$i + 1] ?? '', $m)) {
                $pin = $m[1];
            }
        }

        return $pin ?? null;
    }

    private static function url(FiveMServer $fivem): string
    {
        return 'http://' . $fivem->host() . ':' . $fivem->txAdminPort() . '/';
    }

    private static function fivem(): ?FiveMServer
    {
        return FiveMServer::of(self::server());
    }

    private static function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }
}
