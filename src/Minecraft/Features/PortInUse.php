<?php

namespace Wyvern\Minecraft\Features;

use App\Enums\SubuserPermission;
use Filament\Actions\Action;
use Wyvern\Filament\Actions\PowerActions;

class PortInUse extends ConsoleFix
{
    public function getListeners(): array
    {
        return ['failed to bind to port', 'address already in use'];
    }

    public function getId(): string
    {
        return 'mc_port';
    }

    public function getAction(): Action
    {
        $server = $this->server();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-plug-x')
            ->modalHeading(trans('wyvern.console_fixes.port.heading'))
            ->modalDescription(trans('wyvern.console_fixes.port.description', ['port' => $server->allocation->port]))
            ->modalSubmitActionLabel(trans('server/console.power_actions.restart'))
            ->modalSubmitAction(fn (Action $action) => $action->visible(user()?->can(SubuserPermission::ControlRestart, $server) ?? false))
            ->action(fn () => PowerActions::send('restart'));
    }
}
