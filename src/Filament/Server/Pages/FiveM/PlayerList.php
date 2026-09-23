<?php

namespace Wyvern\Filament\Server\Pages\FiveM;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\Actions\TxAdminAction;
use Wyvern\Filament\Server\Pages\FiveM\Concerns\FiveMPage;

/** Who is on a FiveM server, from the players.json every FXServer publishes. */
class PlayerList extends Page
{
    use FiveMPage;

    public const PERMISSION = 'fivem.players';

    public const LABEL = 'wyvern.players.title';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Users;

    protected static ?int $navigationSort = 1;

    protected string $view = 'wyvern.server.fivem.players';

    /** @var array<string, mixed> */
    private array $memo = [];

    public function isRunning(): bool
    {
        return $this->memo['running'] ??= $this->server()->retrieveStatus()->isStartingOrRunning();
    }

    /** @return list<array{id: int, name: string, ping: int, identifiers: list<string>}>|null null when the server does not answer */
    public function players(): ?array
    {
        if (!$this->isRunning()) {
            return null;
        }

        return $this->memo['players'] ??= $this->fetch('players.json');
    }

    /** @return array{clients?: int, sv_maxclients?: int|string, hostname?: string}|null */
    public function dynamic(): ?array
    {
        if (!$this->isRunning()) {
            return null;
        }

        return $this->memo['dynamic'] ??= $this->fetch('dynamic.json');
    }

    /** @return array<mixed>|null */
    private function fetch(string $file): ?array
    {
        $fivem = $this->fivem();

        try {
            $response = Http::timeout(2)->get('http://' . $fivem->host() . ':' . $fivem->port() . '/' . $file);
        } catch (\Throwable) {
            return null;
        }

        return $response->successful() && is_array($response->json()) ? $response->json() : null;
    }

    /**
     * license:…, discord:…, steam:… — the IP is left out.
     *
     * @param  array<string, mixed>  $player
     * @return list<string>
     */
    public static function identifiers(array $player): array
    {
        return array_values(array_filter(
            (array) ($player['identifiers'] ?? []),
            fn ($id) => is_string($id) && !str_starts_with($id, 'ip:'),
        ));
    }

    public function kickAction(): Action
    {
        return Action::make('kick')
            ->label(trans('wyvern.players.actions.kick'))
            ->modalHeading(fn (array $arguments) => trans('wyvern.players.actions.kick_heading', ['name' => $arguments['name'] ?? '']))
            ->schema([TextInput::make('reason')->label(trans('wyvern.players.fields.reason'))->maxLength(200)])
            ->modalSubmitActionLabel(trans('wyvern.players.actions.kick'))
            ->action(function (array $data, array $arguments) {
                abort_unless(self::canAccess() && user()?->can(SubuserPermission::ControlConsole, $this->server()), 403);

                $id = (int) ($arguments['id'] ?? 0);
                $reason = trim((string) preg_replace('/[\x00-\x1F\x7F"]+/u', ' ', (string) ($data['reason'] ?? '')));

                if ($id <= 0) {
                    return;
                }

                $this->server()->send(trim("clientkick $id " . ($reason !== '' ? '"' . mb_substr($reason, 0, 200) . '"' : '')));

                Activity::event('server:wyvern.players.kick')->property('name', $arguments['name'] ?? (string) $id)->log();
                Notification::make()->title(trans('wyvern.fivem.players.kicked', ['name' => $arguments['name'] ?? $id]))->success()->send();

                $this->memo = [];
            });
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [TxAdminAction::make(), PowerActions::group()];
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.game');
    }
}
