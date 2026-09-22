<?php

namespace Wyvern\Jobs;

use App\Enums\ServerState;
use App\Facades\Activity;
use App\Filament\Server\Pages\Console;
use App\Models\Backup;
use App\Models\Server;
use App\Models\User;
use App\Services\Backups\InitiateBackupService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use Wyvern\Content\Modpack\ModpackIndex;
use Wyvern\Content\Modpack\ModpackInstaller;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\Reinstaller;
use Wyvern\Minecraft\VersionCatalogue;

/**
 * Anything that takes minutes: an optional backup, a switch of flavour or version, and a
 * modpack. Each step waits for the one before it on the node, then the user is notified.
 */
class ChangeServerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const POLL_SECONDS = 5;

    public int $timeout = 3600;

    public int $tries = 1;

    /**
     * @param  array<string, string>  $variables  validated MC_* values; empty for no switch
     * @param  array{url: string, project: ?string, version: ?string, name: string, switch: bool, replace_mods: bool}|null  $modpack
     */
    public function __construct(
        public Server $server,
        public ?User $user,
        public array $variables = [],
        public ?string $image = null,
        public bool $backup = false,
        public ?array $modpack = null,
    ) {}

    public function handle(Reinstaller $reinstaller, ModpackInstaller $modpacks, InitiateBackupService $backups, VersionCatalogue $catalogue): void
    {
        if ($this->backup) {
            $this->say('backing up');
            $this->waitForBackup($backups->handle($this->server, trans('wyvern.jobs.backup_name', ['date' => now()->format('Y-m-d H:i')])));
        }

        $index = null;

        if ($this->modpack !== null) {
            $index = $modpacks->prepare($this->server, $this->modpack['url'], fn (string $m) => $this->say("modpack: $m"));

            if ($this->modpack['switch']) {
                [$this->variables, $this->image] = $this->switchFor($index, $reinstaller, $catalogue);
            }
        }

        if ($this->variables !== []) {
            $this->say('reinstalling as ' . implode(' ', $this->variables));
            $reinstaller->apply($this->server, $this->variables, $this->image);

            Activity::event('server:wyvern.version')
                ->subject($this->server)
                ->actor($this->user ?? $this->server->user)
                ->property([
                    'loader' => $this->variables['MC_LOADER'] ?? null,
                    'version' => $this->variables['MC_VERSION'] ?? null,
                    'build' => $this->variables['MC_BUILD'] ?? null,
                ])
                ->log();

            if ($index !== null) {
                $this->waitForInstall();
            }
        }

        if ($index !== null) {
            // Without a switch, the pack has to fit what the server already runs.
            if (!$this->modpack['switch']) {
                $modpacks->assertFits($this->server, $index);
            }

            $modpacks->apply(
                $this->server,
                $index,
                fn (string $m) => $this->say("modpack: $m"),
                ['project' => $this->modpack['project'], 'version' => $this->modpack['version']],
                $this->modpack['replace_mods'],
            );
        }

        $this->notify(Notification::make()
            ->success()
            ->title($index !== null
                ? trans('wyvern.jobs.modpack_done', ['name' => trim("{$index->name} {$index->versionId}")])
                : trans('wyvern.jobs.switch_done'))
            ->body(trans('wyvern.jobs.done_body', ['server' => $this->server->name])));
    }

    /** Also runs on timeout, which never reaches handle()'s end. */
    public function failed(Throwable $e): void
    {
        Log::error('wyvern: ' . $e->getMessage(), ['server' => $this->server->uuid]);

        if ($this->modpack !== null) {
            app(ModpackInstaller::class)->discard($this->server);
        }

        $this->notify(Notification::make()
            ->danger()
            ->title(trans('wyvern.jobs.failed'))
            ->body(trans('wyvern.jobs.failed_body', ['server' => $this->server->name, 'error' => $e->getMessage()])));
    }

    /**
     * The flavour, version and loader build a pack pins, and the Java image for it.
     *
     * @return array{array<string, string>, ?string}
     */
    private function switchFor(ModpackIndex $index, Reinstaller $reinstaller, VersionCatalogue $catalogue): array
    {
        $loader = Loader::fromModpackKey((string) $index->loader());
        $version = $index->minecraftVersion();

        if ($loader === null || $version === null) {
            throw new RuntimeException(trans('wyvern.jobs.unknown_pack_target'));
        }

        $values = $reinstaller->validate($this->server, [
            'MC_LOADER' => $loader->value,
            'MC_VERSION' => $version,
            'MC_BUILD' => $index->loaderVersion() ?? 'latest',
        ]);

        return [$values, $reinstaller->imageFor($this->server, $loader, $version, $catalogue)];
    }

    private function waitForBackup(Backup $backup): void
    {
        $deadline = time() + 1800;

        while (time() < $deadline) {
            sleep(self::POLL_SECONDS);
            $backup->refresh();

            if ($backup->completed_at !== null) {
                if (!$backup->is_successful) {
                    throw new RuntimeException(trans('wyvern.jobs.backup_failed'));
                }

                return;
            }
        }

        throw new RuntimeException(trans('wyvern.jobs.backup_timeout'));
    }

    private function waitForInstall(): void
    {
        $deadline = time() + 1800;

        while (time() < $deadline) {
            sleep(self::POLL_SECONDS);
            $status = $this->server->refresh()->status;

            if ($status === null) {
                return;
            }

            if (in_array($status, [ServerState::InstallFailed, ServerState::ReinstallFailed], true)) {
                throw new RuntimeException(trans('wyvern.jobs.install_failed'));
            }
        }

        throw new RuntimeException(trans('wyvern.jobs.install_timeout'));
    }

    private function say(string $message): void
    {
        Log::info("wyvern: $message", ['server' => $this->server->uuid]);
    }

    private function notify(Notification $notification): void
    {
        $user = $this->user ?? $this->server->user;

        $notification
            ->actions([
                Action::make('open')
                    ->button()
                    ->label(trans('notifications.open_server'))
                    ->markAsRead()
                    ->url(Console::getUrl(panel: 'server', tenant: $this->server)),
            ])
            ->sendToDatabase($user);
    }
}
