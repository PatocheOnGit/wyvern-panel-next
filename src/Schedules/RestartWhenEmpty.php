<?php

namespace Wyvern\Schedules;

use App\Extensions\Tasks\Schemas\TaskSchema;
use App\Models\Task;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Cache;
use Wyvern\Servers\GameSummary;

/** Restarts only when nobody is online. A server that cannot say how many are is left alone. */
final class RestartWhenEmpty extends TaskSchema
{
    public function __construct(
        private readonly GameSummary $summary,
        private readonly DaemonServerRepository $daemon,
    ) {}

    public function getId(): string
    {
        return 'wyvern_restart_empty';
    }

    public function getName(): string
    {
        return trans('wyvern.schedules.empty.title');
    }

    public function runTask(Task $task): void
    {
        $server = $task->server;

        // A fresh count, not the one cached for the cards.
        Cache::forget("wyvern.summary.players.{$server->uuid}");
        $players = $this->summary->players($server);

        if ($players === null || $players['online'] > 0) {
            return;
        }

        $this->daemon->setServer($server)->power('restart');
    }

    public function formatPayload(string $payload): ?string
    {
        return null;
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [Text::make(trans('wyvern.schedules.empty.help'))];
    }
}
