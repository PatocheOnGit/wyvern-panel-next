<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * Mojang's own launcher metadata.
 *
 * The manifest lists every version ever published with a per-version metadata url; the
 * server jar only appears inside that second document, so resolving a download is two
 * requests. Both are cached, because the manifest alone is nearly a megabyte.
 */
class VanillaCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const MANIFEST = 'https://launchermeta.mojang.com/mc/game/version_manifest_v2.json';

    public function loader(): Loader
    {
        return Loader::Vanilla;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            $manifest = Http::timeout(10)->get(self::MANIFEST);

            if ($manifest->failed()) {
                return [];
            }

            // Releases only: snapshots change under people's feet and are not what a
            // server picker should offer by default.
            return collect($manifest->json('versions', []))
                ->where('type', 'release')
                ->pluck('id')
                ->values()
                ->all();
        });
    }

    public function builds(string $gameVersion): array
    {
        return [];
    }

    /** The Java major Mojang ships this version with. */
    public function javaVersion(string $gameVersion): ?int
    {
        return $this->remember("java.{$gameVersion}", function () use ($gameVersion): ?int {
            $url = $this->metadataUrls()[$gameVersion] ?? null;

            if (!$url) {
                return null;
            }

            $meta = Http::timeout(10)->get($url);

            return $meta->successful() ? ($meta->json('javaVersion.majorVersion') ?: null) : null;
        });
    }

    /** @return array<string, string> */
    private function metadataUrls(): array
    {
        return $this->remember('urls', function (): array {
            $manifest = Http::timeout(10)->get(self::MANIFEST);

            return $manifest->failed() ? [] : collect($manifest->json('versions', []))->pluck('url', 'id')->all();
        });
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        return $this->remember("binary.{$gameVersion}", function () use ($gameVersion): ?ServerBinary {
            $manifest = Http::timeout(10)->get(self::MANIFEST);

            if ($manifest->failed()) {
                return null;
            }

            $entry = collect($manifest->json('versions', []))->firstWhere('id', $gameVersion);

            if (!$entry) {
                return null;
            }

            $meta = Http::timeout(10)->get($entry['url']);

            if ($meta->failed()) {
                return null;
            }

            $server = $meta->json('downloads.server');

            if (!$server) {
                return null;
            }

            return new ServerBinary(
                loader: Loader::Vanilla,
                gameVersion: $gameVersion,
                build: null,
                url: $server['url'],
                sha256: null,
                size: $server['size'] ?? null,
            );
        });
    }
}
