<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * Purpur's own API. Versions come oldest-first, builds are plain integers, and the
 * download is a stable url rather than something to look up — so no second request.
 */
class PurpurCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const BASE = 'https://api.purpurmc.org/v2/purpur';

    public function loader(): Loader
    {
        return Loader::Purpur;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $response = Http::timeout(10)->get(self::BASE);

            if ($response->failed()) {
                return [];
            }

            return array_reverse($response->json('versions', []));
        });
    }

    public function builds(string $gameVersion): array
    {
        return $this->remember("builds.{$gameVersion}", function () use ($gameVersion): array {
            $response = Http::timeout(10)->get(self::BASE . "/{$gameVersion}");

            if ($response->failed()) {
                return [];
            }

            return array_reverse(array_map('strval', $response->json('builds.all', [])));
        });
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        $build ??= $this->remember("latest.{$gameVersion}", function () use ($gameVersion): ?string {
            $response = Http::timeout(10)->get(self::BASE . "/{$gameVersion}");

            return $response->successful() ? (string) $response->json('builds.latest') : null;
        });

        if ($build === null) {
            return null;
        }

        return new ServerBinary(
            loader: Loader::Purpur,
            gameVersion: $gameVersion,
            build: $build,
            url: self::BASE . "/{$gameVersion}/{$build}/download",
        );
    }
}
