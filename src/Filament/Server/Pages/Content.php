<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Models\EggVariable;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Traits\Filament\BlockAccessInConflict;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Livewire\Attributes\Url;
use Wyvern\Content\ContentFile;
use Wyvern\Content\ContentInstaller;
use Wyvern\Content\ContentLibrary;
use Wyvern\Content\ContentProject;
use Wyvern\Content\ContentType;
use Wyvern\Content\Contracts\ContentSource;
use Wyvern\Content\InstalledContent;
use Wyvern\Content\ServerProfile;
use Wyvern\Content\Sources\ModrinthSource;
use Wyvern\Filament\Forms\BackupToggle;
use Wyvern\Jobs\ChangeServerJob;
use Wyvern\Minecraft\InstallRecords;

/**
 * Browse Modrinth or CurseForge and install onto this server, and manage what is there.
 *
 * The server's loader decides what is on offer: a Paper server is shown plugins, a
 * NeoForge one mods and modpacks. A server on the Wyvern egg is also shown modpacks
 * whatever it runs, because installing one can switch the server over first.
 */
class Content extends Page
{
    use BlockAccessInConflict;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?int $navigationSort = 2;

    protected string $view = 'wyvern.server.content';

    #[Url]
    public string $view_mode = 'browse';

    #[Url]
    public string $source = 'modrinth';

    #[Url]
    public string $type = 'mod';

    #[Url]
    public string $search = '';

    #[Url]
    public string $sort = 'relevance';

    #[Url]
    public string $category = '';

    /** @var array<string, array{id: string, versionName: string, url: ?string, filename: string, sha1: ?string, project: ?string}> path => newer file */
    public array $updates = [];

    public bool $checkedUpdates = false;

    protected ContentLibrary $library;

    protected ContentInstaller $installer;

    protected InstallRecords $records;

    protected InstalledContent $installed;

    protected DaemonFileRepository $files;

    protected ModrinthSource $modrinth;

    /** @var array<string, mixed> */
    private array $memo = [];

    public function boot(
        ContentLibrary $library,
        ContentInstaller $installer,
        InstallRecords $records,
        InstalledContent $installed,
        DaemonFileRepository $files,
        ModrinthSource $modrinth,
    ): void {
        $this->library = $library;
        $this->installer = $installer;
        $this->records = $records;
        $this->installed = $installed;
        $this->files = $files;
        $this->modrinth = $modrinth;
    }

    public function mount(): void
    {
        // "?view=installed", as the console fixes link it.
        if (request()->query('view') === 'installed') {
            $this->view_mode = 'installed';
        }

        if (!$this->currentSource()) {
            $this->source = ($this->sources()[0] ?? null)?->key() ?? 'modrinth';
        }

        $this->landOnUsableType();
    }

    public function updatedSearch(): void
    {
        // Livewire re-renders, results() runs again. Nothing else to do.
    }

    public function selectView(string $view): void
    {
        $this->view_mode = $view === 'installed' ? 'installed' : 'browse';
    }

    public function selectType(string $type): void
    {
        $this->type = $type;
        // Categories belong to a project type.
        $this->category = '';
    }

    public function selectSource(string $source): void
    {
        if ($this->library->source($source)?->isAvailable()) {
            $this->source = $source;
            $this->landOnUsableType();
        }
    }

    /** @return ContentSource[] */
    public function sources(): array
    {
        return $this->library->available();
    }

    public function currentSource(): ?ContentSource
    {
        $source = $this->library->source($this->source);

        return $source?->isAvailable() ? $source : null;
    }

    private function landOnUsableType(): void
    {
        $types = $this->availableTypes();

        if ($types !== [] && !in_array($this->currentType(), $types, true)) {
            $this->type = $types[0]->value;
        }
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function profile(): ServerProfile
    {
        return $this->memo['profile'] ??= ServerProfile::of($this->server());
    }

    public function gameVersion(): ?string
    {
        return $this->memo['version'] ??= $this->profile()->gameVersion($this->records);
    }

    public function currentType(): ContentType
    {
        return ContentType::tryFrom($this->type) ?? ContentType::Mod;
    }

    /** @return ContentType[] */
    public function availableTypes(): array
    {
        $types = $this->profile()->installableTypes();

        if (self::canSwitch($this->server()) && !in_array(ContentType::Modpack, $types, true)) {
            $types[] = ContentType::Modpack;
        }

        // Only Modrinth's .mrpack format has an installer.
        if ($this->source !== 'modrinth') {
            $types = array_values(array_filter($types, fn (ContentType $type) => $type !== ContentType::Modpack));
        }

        return $types;
    }

    /** On the Wyvern egg, a modpack can bring its own flavour and version with it. */
    public static function canSwitch(Server $server): bool
    {
        return (user()?->can(SubuserPermission::SettingsReinstall, $server) ?? false)
            && EggVariable::query()->where('egg_id', $server->egg_id)->where('env_variable', 'MC_LOADER')->exists();
    }

    /** @return array<string, string> slug => label, Modrinth only */
    public function categories(): array
    {
        return $this->source === 'modrinth' ? $this->modrinth->categories($this->currentType()) : [];
    }

    public function updatedCategory(): void
    {
        // Livewire re-renders, results() runs again.
    }

    /** @return ContentProject[] */
    public function results(): array
    {
        $profile = $this->profile();
        $type = $this->currentType();

        if (!$this->currentSource() || !in_array($type, $this->availableTypes(), true)) {
            return [];
        }

        // A pack that can switch the server is not narrowed to what it runs today.
        $open = $type === ContentType::Modpack && self::canSwitch($this->server());

        if (!$open && !$profile->isKnown()) {
            return [];
        }

        return $this->library->search(
            $this->source,
            $this->search,
            $type,
            $open ? null : $profile->loader,
            $open ? null : $this->gameVersion(),
            24,
            in_array($this->sort, ContentSource::SORTS, true) ? $this->sort : 'relevance',
            $this->category !== '' ? $this->category : null,
        );
    }

    /**
     * Releases of a project this server can run, newest first. Falls back to any Minecraft
     * version when none matches, so an unpinned or brand-new server still sees something.
     *
     * @return list<ContentFile>
     */
    private function projectFiles(string $projectId): array
    {
        return $this->memo["files.$projectId"] ??= (function () use ($projectId) {
            $loader = $this->profile()->loader;
            $files = $this->library->files($this->source, $projectId, $loader, $this->gameVersion());

            return $files !== [] ? $files : $this->library->files($this->source, $projectId, $loader);
        })();
    }

    /**
     * @param  list<ContentFile>  $files
     * @return array<string, string> file id => "1.2.3 · 1.21.1, 1.21 · beta"
     */
    private function versionOptions(array $files): array
    {
        $options = [];

        foreach ($files as $file) {
            if ($file->isDownloadable()) {
                $options[$file->id] = implode(' · ', array_filter([
                    $file->versionName,
                    implode(', ', array_slice($file->gameVersions, 0, 3)),
                    $file->releaseType !== 'release' ? $file->releaseType : null,
                ]));
            }
        }

        return $options;
    }

    public function install(string $projectId, ?string $fileId = null): void
    {
        $server = $this->server();
        $type = $this->currentType();

        if (!user()?->can(SubuserPermission::FileCreate, $server)) {
            $this->fail(trans('wyvern.content.errors.not_allowed'));

            return;
        }

        if (!$this->currentSource() || !in_array($type, $this->availableTypes(), true) || $type === ContentType::Modpack) {
            $this->fail(trans('wyvern.content.errors.nothing_to_install'));

            return;
        }

        $file = collect($this->projectFiles($projectId))
            ->first(fn (ContentFile $f) => $f->isDownloadable() && ($fileId === null || $f->id === $fileId));

        if (!$file) {
            $this->fail(trans('wyvern.content.errors.nothing_to_install'));

            return;
        }

        try {
            $paths = $this->installer->installWithDependencies($server, $file, $type);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return;
        }

        Activity::event('server:wyvern.content')
            ->property(['project' => $projectId, 'file' => implode(', ', $paths)])
            ->log();

        $dependencies = array_map('basename', array_slice($paths, 1));

        Notification::make()
            ->title(trans('wyvern.content.notifications.installed', ['file' => basename($paths[0])]))
            ->body($dependencies === []
                ? trans('wyvern.content.notifications.installed_body')
                : trans('wyvern.content.notifications.installed_with', ['files' => implode(', ', $dependencies)]))
            ->success()
            ->send();
    }

    public function chooseVersionAction(): Action
    {
        return Action::make('chooseVersion')
            ->label(trans('wyvern.content.versions.action'))
            ->modalHeading(fn (array $arguments) => trans('wyvern.content.versions.heading', ['name' => $arguments['title'] ?? '']))
            ->schema(fn (array $arguments) => [
                Select::make('file')
                    ->label(trans('wyvern.content.versions.label'))
                    ->options($this->versionOptions($this->projectFiles((string) ($arguments['project'] ?? ''))))
                    ->helperText(trans('wyvern.content.versions.help'))
                    ->searchable()
                    ->native(false)
                    ->required(),
            ])
            ->fillForm(fn (array $arguments) => [
                'file' => array_key_first($this->versionOptions($this->projectFiles((string) ($arguments['project'] ?? '')))),
            ])
            ->modalSubmitActionLabel(trans('wyvern.content.install'))
            ->action(fn (array $data, array $arguments) => $this->install((string) ($arguments['project'] ?? ''), (string) $data['file']));
    }

    public function installModpackAction(): Action
    {
        return Action::make('installModpack')
            ->label(trans('wyvern.content.install'))
            ->modalIcon('tabler-packages')
            ->modalHeading(fn (array $arguments) => trans('wyvern.content.modpack.heading', ['name' => $arguments['title'] ?? '']))
            ->fillForm(function (array $arguments) {
                $first = $this->modpackFile((string) ($arguments['project'] ?? ''));

                return ['file' => $first?->id, 'replace_mods' => !$this->modpackFits($first), 'backup' => false];
            })
            ->schema(fn (array $arguments) => [
                Select::make('file')
                    ->label(trans('wyvern.content.versions.label'))
                    ->options($this->versionOptions($this->modpackFiles((string) ($arguments['project'] ?? ''))))
                    ->selectablePlaceholder(false)
                    ->native(false)
                    ->live()
                    ->required(),
                Text::make(fn (Get $get) => $this->modpackPlan((string) ($arguments['project'] ?? ''), $get('file'))),
                Toggle::make('replace_mods')
                    ->label(trans('wyvern.content.modpack.replace_mods'))
                    ->helperText(trans('wyvern.content.modpack.replace_mods_help')),
                BackupToggle::make($this->server()),
            ])
            ->modalSubmitActionLabel(trans('wyvern.content.install'))
            ->action(function (array $data, array $arguments) {
                $server = $this->server();
                $project = (string) ($arguments['project'] ?? '');
                $file = $this->modpackFile($project, $data['file'] ?? null);

                abort_unless(user()?->can(SubuserPermission::FileCreate, $server), 403);

                if (!$file?->isDownloadable()) {
                    $this->fail(trans('wyvern.content.errors.nothing_to_install'));

                    return;
                }

                $switch = !$this->modpackFits($file);

                if ($switch && !self::canSwitch($server)) {
                    $this->fail(trans('wyvern.content.errors.wrong_type', ['loader' => $this->profile()->loader?->label() ?? '?']));

                    return;
                }

                ChangeServerJob::dispatch($server, user(), [], null, ($data['backup'] ?? false) && BackupToggle::available($server), [
                    'url' => $file->url,
                    'project' => $file->projectId ?? $project,
                    'version' => $file->id,
                    'name' => (string) ($arguments['title'] ?? $project),
                    'switch' => $switch,
                    'replace_mods' => (bool) ($data['replace_mods'] ?? false),
                ]);

                Activity::event('server:wyvern.modpack')->property('project', $arguments['title'] ?? $project)->log();

                Notification::make()
                    ->title(trans('wyvern.content.notifications.modpack_queued'))
                    ->body(trans('wyvern.content.notifications.modpack_queued_body'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Versions of a pack this server can take, switching if it may.
     *
     * @return list<ContentFile>
     */
    private function modpackFiles(string $project): array
    {
        if ($project === '') {
            return [];
        }

        return $this->memo["pack.$project"] ??= (function () use ($project) {
            $open = self::canSwitch($this->server());
            $files = $this->library->files('modrinth', $project, $open ? null : $this->profile()->loader, $open ? null : $this->gameVersion());

            return array_values(array_filter($files, fn (ContentFile $f) => $f->isDownloadable()
                && array_intersect($f->loaders, ['fabric', 'quilt', 'forge', 'neoforge']) !== []));
        })();
    }

    private function modpackFile(string $project, ?string $fileId = null): ?ContentFile
    {
        return collect($this->modpackFiles($project))->first(fn (ContentFile $f) => $fileId === null || $f->id === $fileId);
    }

    private function modpackFits(?ContentFile $file): bool
    {
        $loader = $this->profile()->loader;
        $version = $this->gameVersion();

        return $file !== null
            && $loader !== null
            && in_array($loader->value, $file->loaders, true)
            && $version !== null
            && in_array($version, $file->gameVersions, true);
    }

    private function modpackPlan(string $project, ?string $fileId = null): string
    {
        $file = $this->modpackFile($project, $fileId);

        if ($file === null) {
            return trans('wyvern.content.errors.nothing_to_install');
        }

        if ($this->modpackFits($file)) {
            return trans('wyvern.content.modpack.fits', ['version' => $file->versionName]);
        }

        return trans('wyvern.content.modpack.switch', [
            'version' => $file->versionName,
            'loaders' => implode(', ', array_map('ucfirst', $file->loaders)),
            'minecraft' => implode(', ', array_slice($file->gameVersions, 0, 3)),
        ]);
    }

    /* ---- installed ------------------------------------------------------ */

    public function contentDirectory(): ?string
    {
        $directory = $this->profile()->loader?->contentDirectory();

        return filled($directory) ? $directory : null;
    }

    /**
     * What is in plugins/ or mods/, with what the panel knows about each file.
     *
     * @return list<array{path: string, key: string, name: string, enabled: bool, size: int, record: ?array<string, mixed>, title: ?string, icon: ?string}>
     */
    public function installedFiles(): array
    {
        if (isset($this->memo['installed'])) {
            return $this->memo['installed'];
        }

        $directory = $this->contentDirectory();

        if ($directory === null) {
            return $this->memo['installed'] = [];
        }

        try {
            $entries = $this->files->setServer($this->server())->getDirectory('/' . $directory);
        } catch (\Throwable) {
            $entries = [];
        }

        $records = $this->installed->read($this->server())['files'];
        $rows = [];

        foreach ($entries as $entry) {
            $name = (string) ($entry['name'] ?? '');

            if (!($entry['file'] ?? false) || !preg_match('/\.jar(\.disabled)?$/i', $name)) {
                continue;
            }

            $path = "$directory/$name";
            $key = InstalledContent::key($path);

            $rows[] = [
                'path' => $path,
                'key' => $key,
                'name' => $name,
                'enabled' => !str_ends_with(strtolower($name), '.disabled'),
                'size' => (int) ($entry['size'] ?? 0),
                'record' => $records[$key] ?? null,
                'title' => null,
                'icon' => null,
            ];
        }

        $meta = $this->modrinth->projects(array_filter(array_map(fn ($r) => $r['record']['project'] ?? null, $rows)));

        foreach ($rows as &$row) {
            $project = $meta[$row['record']['project'] ?? ''] ?? null;
            $row['title'] = $project['title'] ?? null;
            $row['icon'] = $project['icon'] ?? null;
        }
        unset($row);

        usort($rows, fn ($a, $b) => strcasecmp($a['title'] ?? $a['name'], $b['title'] ?? $b['name']));

        return $this->memo['installed'] = $rows;
    }

    /** @return array<string, mixed>|null */
    public function installedModpack(): ?array
    {
        return $this->memo['modpack'] ??= $this->installed->read($this->server())['modpack'];
    }

    public function toggleFile(string $path): void
    {
        $this->authorizeFiles(SubuserPermission::FileUpdate);
        $row = $this->row($path);

        if ($row === null) {
            return;
        }

        $target = $row['enabled'] ? $row['path'] . '.disabled' : InstalledContent::key($row['path']);

        $this->attempt(fn () => $this->files->setServer($this->server())->renameFiles('/', [['from' => $row['path'], 'to' => $target]]));
    }

    public function deleteFile(string $path): void
    {
        $this->authorizeFiles(SubuserPermission::FileDelete);
        $row = $this->row($path);

        if ($row === null) {
            return;
        }

        $this->attempt(function () use ($row) {
            $this->files->setServer($this->server())->deleteFiles('/', [$row['path']]);
            $this->installed->forget($this->server(), $row['path']);
            unset($this->updates[$row['key']]);
        });
    }

    public function checkUpdates(): void
    {
        $loader = $this->profile()->loader;
        $hashes = [];

        foreach ($this->installedFiles() as $row) {
            if (($row['record']['source'] ?? null) === 'modrinth' && filled($row['record']['sha1'] ?? null)) {
                $hashes[$row['record']['sha1']] = $row['key'];
            }
        }

        $this->updates = [];

        if ($loader !== null && $hashes !== []) {
            foreach ($this->modrinth->updates(array_keys($hashes), $loader, $this->gameVersion()) as $sha1 => $file) {
                $key = $hashes[$sha1] ?? null;

                if ($key !== null && $file->sha1 !== $sha1 && $file->isDownloadable()) {
                    $this->updates[$key] = [
                        'id' => $file->id,
                        'versionName' => $file->versionName,
                        'url' => $file->url,
                        'filename' => $file->filename,
                        'sha1' => $file->sha1,
                        'project' => $file->projectId,
                    ];
                }
            }
        }

        $this->checkedUpdates = true;

        Notification::make()
            ->title(trans_choice('wyvern.content.installed.updates_found', count($this->updates), ['count' => count($this->updates)]))
            ->success()
            ->send();
    }

    public function updateFile(string $path): void
    {
        $this->authorizeFiles(SubuserPermission::FileCreate);
        $row = $this->row($path);
        $update = $row ? ($this->updates[$row['key']] ?? null) : null;

        if ($row === null || $update === null) {
            return;
        }

        $this->attempt(function () use ($row, $update) {
            $server = $this->server();
            $repo = $this->files->setServer($server);
            $directory = dirname($row['path']);
            $target = "$directory/{$update['filename']}";
            // Same name as the old file: download beside it, swap afterwards.
            $download = $target === $row['key'] ? $update['filename'] . '.new' : $update['filename'];

            // Foreground: the old file goes only once the new one is there.
            $repo->pull($update['url'], '/' . $directory, ['filename' => $download, 'foreground' => true]);
            $repo->deleteFiles('/', [$row['path']]);

            if ($download !== $update['filename']) {
                $repo->renameFiles('/', [['from' => "$directory/$download", 'to' => $target]]);
            }

            if (!$row['enabled']) {
                $repo->renameFiles('/', [['from' => $target, 'to' => "$target.disabled"]]);
            }

            $this->installed->forget($server, $row['path']);
            $this->installed->record($server, ["$directory/{$update['filename']}" => [
                'source' => 'modrinth',
                'project' => $update['project'] ?? ($row['record']['project'] ?? null),
                'version' => $update['id'],
                'version_name' => $update['versionName'],
                'sha1' => $update['sha1'],
            ]]);

            unset($this->updates[$row['key']]);

            Activity::event('server:wyvern.content')->property(['project' => $update['project'] ?? '', 'file' => $update['filename']])->log();
        });
    }

    /** @return array{path: string, key: string, name: string, enabled: bool, size: int, record: ?array<string, mixed>, title: ?string, icon: ?string}|null */
    private function row(string $path): ?array
    {
        return collect($this->installedFiles())->firstWhere('path', $path);
    }

    private function authorizeFiles(SubuserPermission $permission): void
    {
        abort_unless(user()?->can($permission, $this->server()), 403);
    }

    private function attempt(callable $change): void
    {
        try {
            $change();
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());
        }

        unset($this->memo['installed'], $this->memo['modpack']);
    }

    private function fail(string $message): void
    {
        Notification::make()
            ->title(trans('wyvern.content.notifications.failed'))
            ->body($message)
            ->danger()
            ->send();
    }

    public static function canAccess(): bool
    {
        /** @var Server|null $server */
        $server = Filament::getTenant();

        return $server !== null
            && !$server->isInConflictState()
            && (ServerProfile::of($server)->installableTypes() !== [] || self::canSwitch($server));
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('wyvern.navigation.software');
    }

    public static function getNavigationLabel(): string
    {
        return trans('wyvern.content.title');
    }

    public function getTitle(): string
    {
        return trans('wyvern.content.title');
    }
}
