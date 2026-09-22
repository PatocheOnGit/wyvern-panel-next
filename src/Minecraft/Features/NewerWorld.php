<?php

namespace Wyvern\Minecraft\Features;

use Filament\Actions\Action;
use Wyvern\Filament\Server\Pages\Version;

class NewerWorld extends ConsoleFix
{
    public function getListeners(): array
    {
        return [
            'saved with a newer version',
            'newer version of minecraft',
            'cannot downgrade',
        ];
    }

    public function getId(): string
    {
        return 'mc_world_version';
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-world')
            ->modalHeading(trans('wyvern.console_fixes.world.heading'))
            ->modalDescription(trans('wyvern.console_fixes.world.description'))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_version'))
            ->action(fn () => redirect(Version::getUrl()));
    }
}
