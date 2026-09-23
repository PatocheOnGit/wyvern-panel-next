<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Server\Resources\Files\Pages\DownloadFiles;
use App\Filament\Server\Resources\Files\Pages\ListFiles;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Wyvern\Content\ServerProfile;
use Wyvern\Filament\Actions\PowerActions;
use Wyvern\Filament\Forms\BackupToggle;
use Wyvern\Jobs\DeleteFileLater;
use Wyvern\Jobs\ResetWorldJob;
use Wyvern\Minecraft\Files\MinecraftFiles;
use Wyvern\Minecraft\InstallRecords;

/** The worlds on a Minecraft server: which one runs, download, reset, switch, and its datapacks. */
class Worlds extends Page
{
    /** Folders that are never worlds, so they are not opened to look for level.dat. */
    private const NOT_WORLDS = ['plugins', 'mods', 'config', 'libraries', 'logs', 'cache', 'versions', 'crash-reports',
        'defaultconfigs', 'kubejs', '.wyvern', '.wyvern-modpack', '.cache', '.paper', 'alpine', 'resources', 'txData'];

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::World;

    protected static ?int $navigationSort = 3;

    protected string $view = 'wyvern.server.worlds';

    protected DaemonFileRepository $files;

    protected MinecraftFiles $minecraft;

    protected InstallRecords $records;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function boot(DaemonFileRepository $files, MinecraftFiles $minecraft, InstallRecords $records): void
    {
        $this->files = $files;
        $this->minecraft = $minecraft;
        $this->records = $records;
    }

    public function levelName(): string
    {
        return $this->memo['level'] ??= ($this->minecraft->properties($this->server())?->get('level-name') ?: 'world');
    }

    public function isRunning(): bool
    {
        return $this->memo['running'] ??= $this->server()->retrieveStatus()->isStartingOrRunning();
    }

    /**
     * Folders holding a level.dat, with the Bukkit-style _nether and _the_end folded into their world.
     *
     * @return list<array{name: string, folders: list<string>, active: bool}>
     */
    public function worlds(): array
    {
        if (isset($this->memo['worlds'])) {
            return $this->memo['worlds'];
        }

        $repo = $this->files->setServer($this->server());

        try {
            $root = $repo->getDirectory('/');
        } catch (\Throwable) {
            return $this->memo['worlds'] = [];
        }

        $candidates = collect($root)
            ->filter(fn ($e) => !($e['file'] ?? true) && !in_array($e['name'] ?? '', self::NOT_WORLDS, true))
            ->pluck('name')
            ->take(20);

        $found = $candidates->filter(function (string $dir) use ($repo) {
            try {
                return collect($repo->getDirectory('/' . $dir))->contains(fn ($e) => ($e['name'] ?? '') === 'level.dat');
            } catch (\Throwable) {
                return false;
            }
        })->values()->all();

        $level = $this->levelName();
        $worlds = [];

        foreach ($found as $dir) {
            $base = preg_replace('/_(nether|the_end)$/', '', $dir);

            // A dimension folder whose world is also here belongs to that world.
            if ($base !== $dir && in_array($base, $found, true)) {
                continue;
            }

            $worlds[] = [
                'name' => $dir,
                'folders' => array_values(array_filter([$dir, "{$dir}_nether", "{$dir}_the_end"], fn ($f) => in_array($f, $found, true))),
                'active' => $dir === $level,
            ];
        }

        usort($worlds, fn ($a, $b) => [$b['active'], $a['name']] <=> [$a['active'], $b['name']]);

        return $this->memo['worlds'] = $worlds;
    }

    /** @return list<array{name: string, size: int, disabled: bool}> */
    public function datapacks(): array
    {
        try {
            $entries = $this->files->setServer($this->server())->getDirectory('/' . $this->levelName() . '/datapacks');
        } catch (\Throwable) {
            return [];
        }

        return collect($entries)
            ->map(fn ($e) => ['name' => (string) $e['name'], 'size' => (int) ($e['size'] ?? 0), 'disabled' => str_ends_with((string) $e['name'], '.disabled')])
            ->sortBy('name')
            ->values()
            ->all();
    }

    public function download(string $world): void
    {
        abort_unless(user()?->can(SubuserPermission::FileArchive, $this->server()) && user()->can(SubuserPermission::FileReadContent, $this->server()), 403);

        $row = collect($this->worlds())->firstWhere('name', $world);

        if ($row === null) {
            return;
        }

        try {
            $archive = $this->files->setServer($this->server())->compressFiles('/', $row['folders'], $world . '-' . now()->format('Ymd-His'), 'tar.gz');
        } catch (\Throwable $e) {
            Notification::make()->title(trans('wyvern.worlds.errors.archive'))->body($e->getMessage())->danger()->send();

            return;
        }

        $name = (string) ($archive['name'] ?? '');
        // The archive exists only to be downloaded.
        DeleteFileLater::dispatch($this->server(), '/' . $name)->delay(now()->addHours(2));

        $this->redirect(DownloadFiles::getUrl(['path' => encode_path('/' . $name)]), navigate: false);
    }

    public function useWorld(string $world): void
    {
        abort_unless(user()?->can(SubuserPermission::FileUpdate, $this->server()), 403);

        $properties = $this->minecraft->properties($this->server());

        if ($properties === null || collect($this->worlds())->firstWhere('name', $world) === null) {
            return;
        }

        $properties->set('level-name', $world);
        $this->minecraft->saveProperties($this->server(), $properties);
        Activity::event('server:wyvern.worlds.use')->property('name', $world)->log();

        Notification::make()->title(trans('wyvern.worlds.used', ['name' => $world]))->body(trans('wyvern.properties.notifications.restart'))->success()->send();
        $this->memo = [];
    }

    public function delete(string $world): void
    {
        abort_unless(user()?->can(SubuserPermission::FileDelete, $this->server()), 403);

        $row = collect($this->worlds())->firstWhere('name', $world);

        if ($row === null || $row['active']) {
            return;
        }

        $this->files->setServer($this->server())->deleteFiles('/', $row['folders']);
        Activity::event('server:wyvern.worlds.delete')->property('name', $world)->log();

        Notification::make()->title(trans('wyvern.worlds.deleted', ['name' => $world]))->success()->send();
        $this->memo = [];
    }

    public function resetAction(): Action
    {
        return Action::make('reset')
            ->label(trans('wyvern.worlds.reset'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments) => trans('wyvern.worlds.reset_heading', ['name' => $arguments['world'] ?? '']))
            ->modalDescription(fn () => $this->isRunning() ? trans('wyvern.worlds.errors.running') : trans('wyvern.worlds.reset_body'))
            ->modalSubmitAction(fn (Action $action) => $this->isRunning() ? false : $action)
            ->schema([
                TextInput::make('seed')->label(trans('wyvern.worlds.seed'))->helperText(trans('wyvern.worlds.seed_help'))->maxLength(64),
                BackupToggle::make($this->server()),
            ])
            ->action(function (array $data, array $arguments) {
                abort_unless(user()?->can(SubuserPermission::FileDelete, $this->server()), 403);

                $row = collect($this->worlds())->firstWhere('name', $arguments['world'] ?? '');

                if ($row === null || $this->isRunning()) {
                    return;
                }

                ResetWorldJob::dispatch(
                    $this->server(),
                    user(),
                    $row['folders'],
                    filled($data['seed'] ?? null) ? (string) $data['seed'] : null,
                    ($data['backup'] ?? false) && BackupToggle::available($this->server()),
                );

                Activity::event('server:wyvern.worlds.reset')->property('name', $row['name'])->log();
                Notification::make()->title(trans('wyvern.worlds.reset_queued'))->success()->send();
            });
    }

    public function deleteDatapack(string $name): void
    {
        abort_unless(user()?->can(SubuserPermission::FileDelete, $this->server()), 403);

        $this->files->setServer($this->server())->deleteFiles('/' . $this->levelName() . '/datapacks', [basename($name)]);
        Notification::make()->title(trans('wyvern.worlds.datapack_deleted', ['name' => $name]))->body(trans('wyvern.worlds.datapack_reload'))->success()->send();
    }

    public function addDatapackAction(): Action
    {
        return Action::make('addDatapack')
            ->label(trans('wyvern.worlds.add_datapack'))
            ->icon(TablerIcon::Plus)
            ->button()
            ->visible(fn () => user()?->can(SubuserPermission::FileCreate, $this->server()) ?? false)
            ->schema([
                Select::make('project')
                    ->label(trans('wyvern.worlds.datapack'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search) => $this->searchDatapacks($search))
                    ->getOptionLabelUsing(fn ($value) => Cache::remember('wyvern.modrinth.title.' . $value, now()->addDay(),
                        fn () => (string) ($this->modrinth()->get('https://api.modrinth.com/v2/project/' . rawurlencode((string) $value))->json('title') ?? $value)))
                    ->helperText(trans('wyvern.worlds.datapack_help'))
                    ->required(),
            ])
            ->action(function (array $data) {
                $version = ServerProfile::of($this->server())->gameVersion($this->records);
                $query = ['loaders' => json_encode(['datapack'])] + ($version !== null ? ['game_versions' => json_encode([$version])] : []);
                $response = $this->modrinth()->get('https://api.modrinth.com/v2/project/' . rawurlencode((string) $data['project']) . '/version', $query);
                $latest = $response->json('0');
                $file = is_array($latest) ? (collect($latest['files'] ?? [])->firstWhere('primary', true) ?? ($latest['files'][0] ?? null)) : null;

                if (!is_array($file) || blank($file['url'] ?? null)) {
                    Notification::make()->title(trans('wyvern.content.errors.nothing_to_install'))->danger()->send();

                    return;
                }

                $this->files->setServer($this->server())->pull($file['url'], '/' . $this->levelName() . '/datapacks', ['filename' => $file['filename'], 'foreground' => true]);
                Activity::event('server:wyvern.content')->property(['project' => $data['project'], 'file' => $file['filename']])->log();

                Notification::make()->title(trans('wyvern.content.notifications.installed', ['file' => $file['filename']]))->body(trans('wyvern.worlds.datapack_reload'))->success()->send();
            });
    }

    /** @return array<string, string> project id => title */
    private function searchDatapacks(string $search): array
    {
        $version = ServerProfile::of($this->server())->gameVersion($this->records);
        $facets = [['categories:datapack']];

        if ($version !== null) {
            $facets[] = ["versions:$version"];
        }

        return collect($this->modrinth()->get('https://api.modrinth.com/v2/search', ['query' => $search, 'facets' => json_encode($facets), 'limit' => 15])->json('hits') ?? [])
            ->mapWithKeys(fn ($hit) => [$hit['project_id'] => $hit['title'] ?? '?'])
            ->all();
    }

    private function modrinth(): PendingRequest
    {
        return Http::timeout(10)->withHeaders(['User-Agent' => config('wyvern.content.user_agent')]);
    }

    /** @return array<Action|ActionGroup> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label(trans('wyvern.worlds.upload'))
                ->icon(TablerIcon::Upload)
                ->color('gray')
                ->button()
                ->modalHeading(trans('wyvern.worlds.upload'))
                ->modalDescription(trans('wyvern.worlds.upload_help'))
                ->modalSubmitActionLabel(trans('wyvern.worlds.open_files'))
                ->action(fn () => $this->redirect(ListFiles::getUrl())),
            PowerActions::group(),
        ];
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
            && !$server->isInConflictState()
            && ServerProfile::of($server)->isKnown()
            && (user()?->can(SubuserPermission::FileRead, $server) ?? false);
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.game');
    }

    public static function getNavigationLabel(): string
    {
        return trans('wyvern.worlds.title');
    }

    public function getTitle(): string
    {
        return trans('wyvern.worlds.title');
    }
}
