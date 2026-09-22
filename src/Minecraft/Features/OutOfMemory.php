<?php

namespace Wyvern\Minecraft\Features;

use Filament\Actions\Action;
use Wyvern\Filament\Server\Pages\Properties;

class OutOfMemory extends ConsoleFix
{
    public function getListeners(): array
    {
        return [
            'java.lang.outofmemoryerror',
            'there is insufficient memory for the java runtime environment',
            'could not reserve enough space for object heap',
        ];
    }

    public function getId(): string
    {
        return 'mc_memory';
    }

    public function getAction(): Action
    {
        $server = $this->server();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-cpu')
            ->modalHeading(trans('wyvern.console_fixes.memory.heading'))
            ->modalDescription(trans('wyvern.console_fixes.memory.description', [
                'memory' => $server->memory > 0
                    ? number_format($server->memory / 1024, 1) . ' GB'
                    : trans('wyvern.console_fixes.memory.unlimited'),
            ]))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_properties'))
            ->action(fn () => redirect(Properties::getUrl()));
    }
}
