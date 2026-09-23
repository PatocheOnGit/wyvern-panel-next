<?php

namespace Wyvern\Schedules;

use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** One step of a restart countdown: a chat warning, or the restart itself. */
class CountdownStep implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public Server $server, public ?string $message) {}

    public function handle(DaemonServerRepository $daemon): void
    {
        // A server stopped in the meantime is left stopped.
        if (!$this->server->retrieveStatus()->isStartingOrRunning()) {
            return;
        }

        if ($this->message !== null) {
            $this->server->send('say ' . $this->message);

            return;
        }

        $daemon->setServer($this->server)->power('restart');
    }
}
