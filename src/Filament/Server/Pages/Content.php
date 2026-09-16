<?php

namespace Wyvern\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Models\Server;
use App\Traits\Filament\BlockAccessInConflict;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use Wyvern\Content\ContentInstaller;
use Wyvern\Content\ContentLibrary;
use Wyvern\Content\ContentProject;
use Wyvern\Content\ContentType;
use Wyvern\Content\ServerProfile;
use Wyvern\Jobs\InstallModpackJob;

/**
 * Browse Modrinth and install onto this server.
 *
 * The server's loader decides what is on offer: a Paper server is shown plugins, a
 * NeoForge one mods and modpacks, and a vanilla one is told there is nothing to install
 * rather than being shown a search that cannot lead anywhere.
 *
 * Plugins and mods are a single pull and land immediately. A modpack is an archive plus
 * dozens of files plus an override tree, so it goes to the queue.
 */
class Content extends Page
{
    use BlockAccessInConflict;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Puzzle;

    protected static ?int $navigationSort = 2;

    protected string $view = 'wyvern.server.content';

    #[Url]
    public string $type = 'mod';

    #[Url]
    public string $search = '';

    public function mount(): void
    {
        $types = $this->profile()->installableTypes();

        // Land on something the server can actually use.
        if ($types !== [] && !in_array($this->currentType(), $types, true)) {
            $this->type = $types[0]->value;
        }
    }

    public function updatedSearch(): void
    {
        // Livewire re-renders, results() runs again. Nothing else to do.
    }

    public function selectType(string $type): void
    {
        $this->type = $type;
    }

    public function server(): Server
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        return $server;
    }

    public function profile(): ServerProfile
    {
        return ServerProfile::of($this->server());
    }

    public function currentType(): ContentType
    {
        return ContentType::tryFrom($this->type) ?? ContentType::Mod;
    }

    /** @return ContentType[] */
    public function availableTypes(): array
    {
        return $this->profile()->installableTypes();
    }

    /** @return ContentProject[] */
    public function results(): array
    {
        $profile = $this->profile();

        if (!$profile->isKnown() || $this->availableTypes() === []) {
            return [];
        }

        return app(ContentLibrary::class)->search(
            'modrinth',
            $this->search,
            $this->currentType(),
            $profile->loader,
            $profile->gameVersion,
            24,
        );
    }

    public function install(string $projectId): void
    {
        $server = $this->server();
        $profile = $this->profile();
        $type = $this->currentType();

        if (!user()?->can(SubuserPermission::FileCreate, $server)) {
            $this->fail(trans('wyvern.content.errors.not_allowed'));

            return;
        }

        $files = app(ContentLibrary::class)->files('modrinth', $projectId, $profile->loader, $profile->gameVersion);
        $file = collect($files)->first(fn ($f) => $f->isDownloadable());

        if (!$file) {
            $this->fail(trans('wyvern.content.errors.nothing_to_install'));

            return;
        }

        if ($type === ContentType::Modpack) {
            InstallModpackJob::dispatch($server, $file->url, $projectId);

            Activity::event('server:wyvern.modpack')->property('project', $projectId)->log();

            Notification::make()
                ->title(trans('wyvern.content.notifications.modpack_queued'))
                ->body(trans('wyvern.content.notifications.modpack_queued_body'))
                ->success()
                ->send();

            return;
        }

        try {
            $path = app(ContentInstaller::class)->install($server, $file, $type);
        } catch (\Throwable $e) {
            $this->fail($e->getMessage());

            return;
        }

        Activity::event('server:wyvern.content')
            ->property(['project' => $projectId, 'file' => $path])
            ->log();

        Notification::make()
            ->title(trans('wyvern.content.notifications.installed', ['file' => basename($path)]))
            ->body(trans('wyvern.content.notifications.installed_body'))
            ->success()
            ->send();
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

        return $server !== null && ServerProfile::of($server)->installableTypes() !== [];
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
