<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * Forge's promotions file.
 *
 * It maps "<mc>-latest" and "<mc>-recommended" to a Forge version, which is the whole
 * catalogue: Forge does not publish an enumerable build list per version, only these
 * two pointers. So a game version offers at most two builds, and they are named rather
 * than numbered.
 *
 * What comes back is an installer, not a server jar — Forge has to be run once on the
 * node before there is anything to launch.
 */
class ForgeCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const PROMOTIONS = 'https://files.minecraftforge.net/net/minecraftforge/forge/promotions_slim.json';

    private const MAVEN = 'https://maven.minecraftforge.net/net/minecraftforge/forge';

    public function loader(): Loader
    {
        return Loader::Forge;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $promos = $this->promotions();

            return collect(array_keys($promos))
                ->map(fn (string $key): string => str($key)->beforeLast('-')->toString())
                ->unique()
                ->sortByDesc(fn (string $v): string => $v, SORT_NATURAL)
                ->values()
                ->all();
        });
    }

    public function builds(string $gameVersion): array
    {
        $promos = $this->promotions();

        return collect(['recommended', 'latest'])
            ->filter(fn (string $channel): bool => isset($promos["{$gameVersion}-{$channel}"]))
            ->values()
            ->all();
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        $promos = $this->promotions();
        $build ??= isset($promos["{$gameVersion}-recommended"]) ? 'recommended' : 'latest';

        $forgeVersion = $promos["{$gameVersion}-{$build}"] ?? null;

        if ($forgeVersion === null) {
            return null;
        }

        $full = "{$gameVersion}-{$forgeVersion}";

        return new ServerBinary(
            loader: Loader::Forge,
            gameVersion: $gameVersion,
            build: $build,
            url: self::MAVEN . "/{$full}/forge-{$full}-installer.jar",
            isInstaller: true,
        );
    }

    /** @return array<string, string> */
    private function promotions(): array
    {
        return $this->remember('promotions', function (): array {
            $response = Http::timeout(10)->get(self::PROMOTIONS);

            return $response->successful() ? $response->json('promos', []) : [];
        });
    }
}
