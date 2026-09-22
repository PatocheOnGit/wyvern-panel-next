<?php

namespace Wyvern\Minecraft\Features;

use Filament\Actions\Action;
use Filament\Schemas\Components\Text;
use Wyvern\Filament\Server\Pages\Content;

class ClientOnlyMod extends ConsoleFix
{
    private const NOT_MODS = ['minecraft', 'forge', 'neoforge', 'fabricloader', 'id', 'loading', 'list', 'the', 'resolution'];

    public function getListeners(): array
    {
        return [
            'attempted to load class net/minecraft/client',
            'for invalid dist dedicated_server',
            'in environment type server',
        ];
    }

    public function getId(): string
    {
        return 'mc_client_mod';
    }

    public function getAction(): Action
    {
        $suspects = $this->suspects();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-device-desktop')
            ->modalHeading(trans('wyvern.console_fixes.client_mod.heading'))
            ->modalDescription(trans($suspects === [] ? 'wyvern.console_fixes.client_mod.unknown' : 'wyvern.console_fixes.client_mod.description'))
            ->schema(array_map(fn (string $id) => Text::make($id)->fontFamily('mono'), $suspects))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_installed'))
            ->action(fn () => redirect(Content::getUrl(['view' => 'installed'])));
    }

    /** @return list<string> mod ids named near the failure */
    private function suspects(): array
    {
        preg_match_all("/(?:for mod|from mod|mod file) '?([a-z0-9_\-]{2,64})'?/i", $this->logTail(16384), $m);

        return array_slice(array_values(array_diff(array_unique(array_map('strtolower', $m[1])), self::NOT_MODS)), 0, 6);
    }
}
