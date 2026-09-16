<?php

namespace Wyvern\Jobs;

use App\Models\Server;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Wyvern\Content\Modpack\ModpackInstaller;

/**
 * Installing a modpack takes minutes: an archive, then dozens of files three at a time,
 * then an override tree. That cannot happen inside a click, so it happens here.
 *
 * Progress is written to the log rather than pushed at the browser — the files appearing
 * in the file manager is the honest progress indicator, and a notification at the end
 * says whether it worked.
 */
class InstallModpackJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 1800;

    public int $tries = 1;

    public function __construct(
        public Server $server,
        public string $archiveUrl,
        public string $packName,
    ) {}

    public function handle(ModpackInstaller $installer): void
    {
        $context = ['server' => $this->server->uuid, 'pack' => $this->packName];

        try {
            $index = $installer->install(
                $this->server,
                $this->archiveUrl,
                fn (string $message) => Log::info("modpack: {$message}", $context),
            );

            Log::info("modpack: installed {$index->name} {$index->versionId}", $context);
        } catch (\Throwable $e) {
            Log::error('modpack: ' . $e->getMessage(), $context);

            throw $e;
        }
    }
}
