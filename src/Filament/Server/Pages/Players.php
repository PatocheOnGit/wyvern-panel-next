<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use RuntimeException;
use Wyvern\Content\ServerProfile;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\Players\PlayerLists;
use Wyvern\Minecraft\Players\StatusPing;

/** Who is online, and the whitelist, operators and bans. */
class Players extends Page
{
    use BlockAccessInConflict;

    public const PERMISSION = 'minecraft.players';

    public const TABS = ['online', 'whitelist', 'ops', 'bans', 'ip_bans'];

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Users;

    protected static ?int $navigationSort = 1;

    protected string $view = 'wyvern.server.players';

    #[Url]
    public string $tab = 'online';

    public string $value = '';

    public string $reason = '';

    protected PlayerLists $lists;

    protected MinecraftFiles $files;

    protected StatusPing $ping;

    /** @var array<string, mixed> read once per request */
    private array $memo = [];

    public function boot(PlayerLists $lists, MinecraftFiles $files, StatusPing $ping): void
    {
        $this->lists = $lists;
        $this->files = $files;
        $this->ping = $ping;
    }

    public function mount(): void
    {
        if (!in_array($this->tab, self::TABS, true)) {
            $this->tab = 'online';
        }
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
            $this->reset('value', 'reason');
        }
    }

    public function isRunning(): bool
    {
        return $this->memo['running'] ??= $this->lists->isRunning($this->server());
    }

    /** @return array{online: int, max: int, players: list<array{name: string, id: string}>, version: ?string}|null */
    public function status(): ?array
    {
        if (!$this->isRunning()) {
            return null;
        }

        return $this->memo['status'] ??= $this->ping->ping($this->server());
    }

    /** @return list<array<string, mixed>> */
    public function entries(string $list): array
    {
        return $this->memo["list.$list"] ??= $this->lists->entries($this->server(), $list);
    }

    /** @return list<array<string, mixed>> */
    public function recent(): array
    {
        return $this->memo['recent'] ??= array_slice($this->lists->recent($this->server()), 0, 30);
    }

    public function whitelistEnabled(): bool
    {
        return $this->memo['whitelist'] ??= ($this->files->properties($this->server())?->get('white-list') === 'true');
    }

    /** @return array<string, bool> lower-cased name => true, per list */
    public function membership(string $list): array
    {
        return $this->memo["members.$list"] ??= collect($this->entries($list))
            ->mapWithKeys(fn (array $e) => [strtolower((string) ($e['name'] ?? '')) => true])
            ->all();
    }

    public function add(): void
    {
        $this->authorizeEdit();

        $this->run(function () {
            $this->lists->add($this->server(), $this->tab, $this->value, $this->reason);
            $this->log('add', $this->tab, $this->value);
            $this->reset('value', 'reason');
        });
    }

    public function remove(string $list, string $value): void
    {
        $this->authorizeEdit();

        $this->run(function () use ($list, $value) {
            $this->lists->remove($this->server(), $list, $value);
            $this->log('remove', $list, $value);
        });
    }

    /** Quick actions from the online and recent lists. */
    public function toggle(string $list, string $name): void
    {
        $this->authorizeEdit();

        $member = isset($this->membership($list)[strtolower($name)]);

        $this->run(function () use ($list, $name, $member) {
            $member
                ? $this->lists->remove($this->server(), $list, $name)
                : $this->lists->add($this->server(), $list, $name);
            $this->log($member ? 'remove' : 'add', $list, $name);
        });
    }

    public function setWhitelist(bool $enabled): void
    {
        $this->authorizeEdit();

        $this->run(function () use ($enabled) {
            $this->lists->setWhitelist($this->server(), $enabled);
            Activity::event('server:wyvern.players.whitelist')->property('enabled', $enabled ? 'on' : 'off')->log();
        });
    }

    public function banAction(): Action
    {
        return Action::make('ban')
            ->label(trans('wyvern.players.actions.ban'))
            ->color('danger')
            ->modalHeading(fn (array $arguments) => trans('wyvern.players.actions.ban_heading', ['name' => $arguments['name'] ?? '']))
            ->schema([TextInput::make('reason')->label(trans('wyvern.players.fields.reason'))->maxLength(200)])
            ->modalSubmitActionLabel(trans('wyvern.players.actions.ban'))
            ->action(function (array $data, array $arguments) {
                $this->authorizeEdit();
                $this->run(function () use ($data, $arguments) {
                    $this->lists->add($this->server(), 'bans', (string) ($arguments['name'] ?? ''), $data['reason'] ?? null);
                    $this->log('add', 'bans', (string) ($arguments['name'] ?? ''));
                });
            });
    }

    public function kickAction(): Action
    {
        return Action::make('kick')
            ->label(trans('wyvern.players.actions.kick'))
            ->modalHeading(fn (array $arguments) => trans('wyvern.players.actions.kick_heading', ['name' => $arguments['name'] ?? '']))
            ->schema([TextInput::make('reason')->label(trans('wyvern.players.fields.reason'))->maxLength(200)])
            ->modalSubmitActionLabel(trans('wyvern.players.actions.kick'))
            ->action(function (array $data, array $arguments) {
                $this->authorizeEdit();
                $this->run(function () use ($data, $arguments) {
                    $this->lists->kick($this->server(), (string) ($arguments['name'] ?? ''), $data['reason'] ?? null);
                    Activity::event('server:wyvern.players.kick')->property('name', $arguments['name'] ?? '')->log();
                });
            });
    }

    private function run(callable $change): void
    {
        try {
            $change();
        } catch (RuntimeException $e) {
            Notification::make()->title(trans('wyvern.players.errors.failed'))->body($e->getMessage())->danger()->send();
        }

        $this->memo = [];
    }

    private function log(string $verb, string $list, string $value): void
    {
        Activity::event("server:wyvern.players.$verb")
            ->property(['list' => trans("wyvern.players.tabs.$list"), 'name' => $value])
            ->log();
    }

    private function authorizeEdit(): void
    {
        abort_unless(self::canAccess(), 403);
    }

    /** @return array<ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [PowerActions::group()];
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public static function canAccess(): bool
    {
        $server = Filament::getTenant();

        return $server instanceof Server
            && ServerProfile::of($server)->isKnown()
            && (user()?->can(self::PERMISSION, $server) ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.game');
    }

    public static function getNavigationLabel(): string
    {
        return trans('wyvern.players.title');
    }

    public function getTitle(): string
    {
        return trans('wyvern.players.title');
    }
}
