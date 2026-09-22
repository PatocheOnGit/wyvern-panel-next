<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/** Quilt's meta service. Like Forge, it hands out an installer that runs on the node. */
class QuiltCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const BASE = 'https://meta.quiltmc.org/v3/versions';

    public function loader(): Loader
    {
        return Loader::Quilt;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $response = Http::timeout(10)->get(self::BASE . '/game');

            return $response->failed() ? [] : collect($response->json() ?? [])
                ->where('stable', true)
                ->pluck('version')
                ->values()
                ->all();
        });
    }

    /** Loader versions, betas left out. */
    public function builds(string $gameVersion): array
    {
        return $this->remember('loaders', function (): array {
            $response = Http::timeout(10)->get(self::BASE . '/loader');

            return $response->failed() ? [] : collect($response->json() ?? [])
                ->pluck('version')
                ->reject(fn (string $version): bool => str_contains($version, '-'))
                ->values()
                ->all();
        });
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        $build ??= $this->builds($gameVersion)[0] ?? null;
        $installer = $this->remember('installer', function (): ?string {
            $response = Http::timeout(10)->get(self::BASE . '/installer');

            return $response->failed() ? null : $response->json('0.url');
        });

        if ($build === null || $installer === null) {
            return null;
        }

        return new ServerBinary(
            loader: Loader::Quilt,
            gameVersion: $gameVersion,
            build: $build,
            url: $installer,
            isInstaller: true,
        );
    }
}
