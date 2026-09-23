<?php

namespace Wyvern\Schedules;

use App\Extensions\Tasks\Schemas\TaskSchema;
use App\Models\Task;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;

/** Warns players in chat, then restarts. The payload is the countdown in minutes. */
final class RestartWithCountdown extends TaskSchema
{
    public const OPTIONS = [1, 5, 10, 15, 30];

    public function getId(): string
    {
        return 'wyvern_restart_countdown';
    }

    public function getName(): string
    {
        return trans('wyvern.schedules.countdown.title');
    }

    public function runTask(Task $task): void
    {
        $server = $task->server;

        if (!$server->retrieveStatus()->isStartingOrRunning()) {
            return;
        }

        $total = max(1, (int) $task->payload) * 60;

        // Warnings at these many seconds before the restart, when the countdown is long enough.
        foreach ([1800, 900, 600, 300, 60, 30, 10] as $left) {
            if ($left <= $total) {
                CountdownStep::dispatch($server, $this->message($left))->delay(now()->addSeconds($total - $left));
            }
        }

        CountdownStep::dispatch($server, null)->delay(now()->addSeconds($total));
    }

    private function message(int $seconds): string
    {
        return $seconds >= 60
            ? trans_choice('wyvern.schedules.countdown.minutes', intdiv($seconds, 60), ['count' => intdiv($seconds, 60)])
            : trans('wyvern.schedules.countdown.seconds', ['count' => $seconds]);
    }

    public function getDefaultPayload(): string
    {
        return '5';
    }

    public function getPayloadLabel(): string
    {
        return trans('wyvern.schedules.countdown.payload');
    }

    public function formatPayload(string $payload): string
    {
        return trans_choice('wyvern.schedules.countdown.format', (int) $payload, ['count' => (int) $payload]);
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            Select::make('payload')
                ->label($this->getPayloadLabel())
                ->options(collect(self::OPTIONS)->mapWithKeys(fn ($m) => [(string) $m => $this->formatPayload((string) $m)])->all())
                ->selectablePlaceholder(false)
                ->required()
                ->default($this->getDefaultPayload()),
        ];
    }
}
