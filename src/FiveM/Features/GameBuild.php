<?php

namespace Wyvern\FiveM\Features;

use Filament\Actions\Action;
use Wyvern\Filament\Server\Pages\FiveM\Config;
use Wyvern\Minecraft\Features\ConsoleFix;

class GameBuild extends ConsoleFix
{
    public function getListeners(): array
    {
        return [
            'is not a valid game build',
            'invalid game build',
            'game build is not supported',
            'unsupported game build',
        ];
    }

    public function getId(): string
    {
        return 'fivem_game_build';
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-versions')
            ->modalHeading(trans('wyvern.console_fixes.fivem_game_build.heading'))
            ->modalDescription(trans('wyvern.console_fixes.fivem_game_build.description'))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_config'))
            ->action(fn () => redirect(Config::getUrl()));
    }
}
