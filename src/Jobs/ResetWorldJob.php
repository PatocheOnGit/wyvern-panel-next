<?php

namespace Wyvern\Jobs;

use App\Models\Server;
use App\Models\User;
use App\Repositories\Daemon\DaemonFileRepository;
use App\Services\Backups\InitiateBackupService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;
use Wyvern\Minecraft\Files\MinecraftFiles;

/** Deletes a world's folders, after an optional backup, and sets the seed for the next one. */
class ResetWorldJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 2400;

    public int $tries = 1;

    /** @param list<string> $folders */
    public function __construct(
        public Server $server,
        public ?User $user,
        public array $folders,
        public ?string $seed,
        public bool $backup,
    ) {}

    public function handle(DaemonFileRepository $files, MinecraftFiles $minecraft, InitiateBackupService $backups): void
    {
        if ($this->backup) {
            $backup = $backups->handle($this->server, trans('wyvern.jobs.backup_name', ['date' => now()->format('Y-m-d H:i')]));
            $deadline = time() + 1800;

            do {
                sleep(5);
                $backup->refresh();
            } while ($backup->completed_at === null && time() < $deadline);

            if (!$backup->is_successful) {
                throw new RuntimeException(trans('wyvern.jobs.backup_failed'));
            }
        }

        if ($this->server->retrieveStatus()->isStartingOrRunning()) {
            throw new RuntimeException(trans('wyvern.worlds.errors.running'));
        }

        $repo = $files->setServer($this->server);
        $world = $this->folders[0];
        $parked = '.wyvern/datapacks-' . $world;

        // Datapacks live inside the world; worldgen ones like Terralith matter most on a fresh world.
        try {
            $keep = collect($repo->getDirectory("/{$world}/datapacks"))->isNotEmpty();
        } catch (Throwable) {
            $keep = false;
        }

        if ($keep) {
            $repo->renameFiles('/', [['from' => "{$world}/datapacks", 'to' => $parked]]);
        }

        $repo->deleteFiles('/', $this->folders);

        if ($keep) {
            $repo->renameFiles('/', [['from' => $parked, 'to' => "{$world}/datapacks"]]);
        }

        if ($this->seed !== null) {
            $properties = $minecraft->properties($this->server);
            $properties?->set('level-seed', $this->seed);
            if ($properties !== null) {
                $minecraft->saveProperties($this->server, $properties);
            }
        }

        $this->notify(Notification::make()->success()->title(trans('wyvern.worlds.reset_done'))
            ->body(trans('wyvern.worlds.reset_done_body', ['server' => $this->server->name])));
    }

    public function failed(Throwable $e): void
    {
        $this->notify(Notification::make()->danger()->title(trans('wyvern.worlds.reset_failed'))
            ->body(trans('wyvern.jobs.failed_body', ['server' => $this->server->name, 'error' => $e->getMessage()])));
    }

    private function notify(Notification $notification): void
    {
        $notification->sendToDatabase($this->user ?? $this->server->user);
    }
}
