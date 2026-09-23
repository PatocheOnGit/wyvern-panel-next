<?php

namespace Wyvern\Filament\Server\Pages\FiveM;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Repositories\Daemon\DaemonFileRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\Actions\TxAdminAction;
use Wyvern\Filament\Server\Pages\FiveM\Concerns\FiveMPage;
use Wyvern\FiveM\FiveMServer;
use Wyvern\FiveM\Resources;
use Wyvern\FiveM\ServerCfg;
use Wyvern\Minecraft\Files\MinecraftFiles;

/** What is in resources/, and which of it server.cfg starts. */
class ResourceList extends Page
{
    use FiveMPage;

    public const PERMISSION = SubuserPermission::FileRead;

    public const LABEL = 'wyvern.fivem.resources.title';

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?int $navigationSort = 3;

    protected string $view = 'wyvern.server.fivem.resources';

    public string $search = '';

    protected Resources $resources;

    protected MinecraftFiles $files;

    protected DaemonFileRepository $daemon;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function boot(Resources $resources, MinecraftFiles $files, DaemonFileRepository $daemon): void
    {
        $this->resources = $resources;
        $this->files = $files;
        $this->daemon = $daemon;
    }

    public function cfg(): ServerCfg
    {
        return $this->memo['cfg'] ??= ServerCfg::parse($this->files->read($this->server(), FiveMServer::CFG) ?? '');
    }

    /** @return list<array{name: string, path: string, category: ?string, ensured: bool, via: ?string, origin: string}> */
    public function rows(): array
    {
        $rows = $this->memo['rows'] ??= $this->resources->list($this->server(), $this->cfg()->ensured());

        return $this->search === ''
            ? $rows
            : array_values(array_filter($rows, fn ($r) => str_contains(strtolower($r['name'] . ' ' . $r['category']), strtolower($this->search))));
    }

    public function toggle(string $name): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        $row = collect($this->rows())->firstWhere('name', $name);

        if ($row === null || $row['via'] !== null) {
            return;
        }

        $cfg = $this->cfg();
        $cfg->setEnsured($name, !$row['ensured']);
        $this->files->write($this->server(), FiveMServer::CFG, $cfg->render());

        // Running: start or stop it now too, rather than at the next restart.
        $live = $this->server()->retrieveStatus()->isStartingOrRunning()
            && user()->can(SubuserPermission::ControlConsole, $this->server());

        if ($live) {
            $this->server()->send(($row['ensured'] ? 'stop ' : 'ensure ') . $name);
        }

        Activity::event('server:wyvern.fivem.resource')->property(['name' => $name, 'state' => $row['ensured'] ? 'off' : 'on'])->log();

        Notification::make()
            ->title(trans($row['ensured'] ? 'wyvern.fivem.resources.stopped' : 'wyvern.fivem.resources.started', ['name' => $name]))
            ->body($live ? trans('wyvern.fivem.resources.live') : trans('wyvern.fivem.resources.next_start'))
            ->success()
            ->send();

        $this->memo = [];
    }

    public function delete(string $name): void
    {
        abort_unless(user()?->can(SubuserPermission::FileDelete, $this->server()), 403);

        $row = collect($this->rows())->firstWhere('name', $name);

        if ($row === null || $row['origin'] !== 'folder') {
            return;
        }

        $cfg = $this->cfg();
        $cfg->setEnsured($name, false);
        $this->files->write($this->server(), FiveMServer::CFG, $cfg->render());
        $this->daemon->setServer($this->server())->deleteFiles(dirname($row['path']), [basename($row['path'])]);

        Activity::event('server:wyvern.fivem.resource')->property(['name' => $name, 'state' => 'deleted'])->log();
        Notification::make()->title(trans('wyvern.fivem.resources.deleted', ['name' => $name]))->success()->send();

        $this->memo = [];
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [TxAdminAction::make(), PowerActions::group()];
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.software');
    }
}
