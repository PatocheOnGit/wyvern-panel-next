<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * Fabric's meta service.
 *
 * Fabric is the only flavour that assembles a launcher for you: give it a game version,
 * a loader version and an installer version and /server/jar returns a ready jar. The
 * build we expose is the loader version, which is what people actually pin.
 */
class FabricCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const BASE = 'https://meta.fabricmc.net/v2/versions';

    public function loader(): Loader
    {
        return Loader::Fabric;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $response = Http::timeout(10)->get(self::BASE . '/game');

            if ($response->failed()) {
                return [];
            }

            return collect($response->json() ?? [])
                ->where('stable', true)
                ->pluck('version')
                ->values()
                ->all();
        });
    }

    public function builds(string $gameVersion): array
    {
        return $this->remember('loaders', function (): array {
            $response = Http::timeout(10)->get(self::BASE . '/loader');

            if ($response->failed()) {
                return [];
            }

            return collect($response->json() ?? [])
                ->where('stable', true)
                ->pluck('version')
                ->values()
                ->all();
        });
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        $build ??= $this->builds($gameVersion)[0] ?? null;
        $installer = $this->installerVersion();

        if ($build === null || $installer === null) {
            return null;
        }

        return new ServerBinary(
            loader: Loader::Fabric,
            gameVersion: $gameVersion,
            build: $build,
            url: self::BASE . "/loader/{$gameVersion}/{$build}/{$installer}/server/jar",
        );
    }

    private function installerVersion(): ?string
    {
        return $this->remember('installer', function (): ?string {
            $response = Http::timeout(10)->get(self::BASE . '/installer');

            if ($response->failed()) {
                return null;
            }

            return collect($response->json() ?? [])
                ->where('stable', true)
                ->value('version');
        });
    }
}
