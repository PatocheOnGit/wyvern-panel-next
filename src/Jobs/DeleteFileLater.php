<?php

namespace Wyvern\Jobs;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Removes an archive made only to be downloaded, once the download has had time to finish. */
class DeleteFileLater implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public Server $server, public string $path) {}

    public function handle(DaemonFileRepository $files): void
    {
        try {
            $files->setServer($this->server)->deleteFiles(dirname($this->path), [basename($this->path)]);
        } catch (\Throwable) {
            // Already gone.
        }
    }
}
