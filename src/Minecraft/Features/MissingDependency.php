<?php

namespace Wyvern\Minecraft\Features;

use Filament\Actions\Action;
use Filament\Schemas\Components\Text;
use Illuminate\Support\HtmlString;
use Wyvern\Filament\Server\Pages\Content;

class MissingDependency extends ConsoleFix
{
    /** Loader and game ids, never something to install. */
    private const IGNORED = ['minecraft', 'java', 'forge', 'neoforge', 'fabricloader', 'fabric-loader', 'quilt_loader'];

    /** Mod ids that are not the Modrinth slug. Fabric API still answers to its old id. */
    private const ALIASES = ['fabric' => 'fabric-api', 'quilted_fabric_api' => 'qsl'];

    public function getListeners(): array
    {
        return [
            'missing or unsupported mandatory dependencies',
            'incompatible mods found',
            'which is missing!',
            'mod resolution encountered an incompatible mod set',
        ];
    }

    public function getId(): string
    {
        return 'mc_dependency';
    }

    public function getAction(): Action
    {
        $missing = $this->missing();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalIcon('tabler-puzzle')
            ->modalHeading(trans('wyvern.console_fixes.dependency.heading'))
            ->modalDescription(trans($missing === [] ? 'wyvern.console_fixes.dependency.unknown' : 'wyvern.console_fixes.dependency.description'))
            ->schema(array_map(
                fn (string $id) => Text::make(new HtmlString(sprintf(
                    '<a class="wy-link" href="%s">%s</a>',
                    e(Content::getUrl(['type' => 'mod', 'search' => $id])),
                    e($id),
                )))->fontFamily('mono'),
                $missing,
            ))
            ->modalSubmitActionLabel(trans('wyvern.console_fixes.open_content'))
            ->action(fn () => redirect(Content::getUrl(['type' => 'mod', 'search' => $missing[0] ?? ''])));
    }

    /** @return list<string> mod ids the log says are missing */
    private function missing(): array
    {
        $log = $this->logTail();
        $patterns = [
            // Fabric and Quilt: "requires any version of fabric-api, which is missing!"
            '/of ([a-z0-9_\-]+),? which is missing/i',
            // Newer Fabric: "- Install fabric-api, any version."
            '/- Install ([a-z0-9_\-]+),/i',
            // Forge and NeoForge: "Mod ID: 'architectury', Requested by: 'rei'"
            "/Mod ID: '([^']+)', Requested by/i",
        ];

        $ids = [];

        foreach ($patterns as $pattern) {
            preg_match_all($pattern, $log, $m);
            $ids = [...$ids, ...$m[1]];
        }

        $ids = array_map(fn (string $id) => self::ALIASES[strtolower($id)] ?? strtolower($id), $ids);

        return array_values(array_diff(array_unique($ids), self::IGNORED));
    }
}
