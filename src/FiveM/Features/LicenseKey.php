<?php

namespace Wyvern\FiveM\Features;

use App\Filament\Server\Pages\Startup;
use Filament\Actions\Action;
use Wyvern\Minecraft\Features\ConsoleFix;

class LicenseKey extends ConsoleFix
{
    public function getListeners(): array
    {
        return [
            'does not have a license key specified',
            'license key authentication failed',
            'invalid license key',
        ];
    }

    public function getId(): string
    {
        return 'fivem_license';
    }

    public function getAction(): Action
    {
        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-key')
            ->modalHeading(trans('wyvern.console_fixes.fivem_license.heading'))
            ->modalDescription(trans('wyvern.console_fixes.fivem_license.description'))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_startup'))
            ->action(fn () => redirect(Startup::getUrl()));
    }
}
