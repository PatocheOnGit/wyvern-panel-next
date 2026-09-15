<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Contracts\LoaderCatalogue;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\ServerBinary;

/**
 * NeoForge's Maven metadata.
 *
 * NeoForge does not publish which Minecraft version a build targets — it encodes it in
 * the version string, under two schemes at once. Both were checked against Mojang's own
 * manifest rather than assumed:
 *
 *   21.11.7      three parts, the pre-26 scheme: Minecraft 1.21.11
 *   26.2.0.88    four parts, the current scheme: family 26.2.0, Minecraft 26.2
 *   26.1.2.109   four parts: family 26.1.2, Minecraft 26.1.2
 *
 * The rule that satisfies all three: drop the build, then drop a trailing ".0". 26.2.0
 * becomes 26.2, which is in the manifest; 26.2.0 itself is not.
 *
 * What comes back is an installer, not a server jar.
 */
class NeoForgeCatalogue extends CachedCatalogue implements LoaderCatalogue
{
    private const METADATA = 'https://maven.neoforged.net/releases/net/neoforged/neoforge/maven-metadata.xml';

    private const MAVEN = 'https://maven.neoforged.net/releases/net/neoforged/neoforge';

    public function loader(): Loader
    {
        return Loader::NeoForge;
    }

    public function gameVersions(): array
    {
        return $this->remember('versions', function (): array {
            return collect($this->versionsByGame())->keys()->values()->all();
        });
    }

    public function builds(string $gameVersion): array
    {
        return $this->versionsByGame()[$gameVersion] ?? [];
    }

    public function binary(string $gameVersion, ?string $build = null): ?ServerBinary
    {
        $build ??= $this->builds($gameVersion)[0] ?? null;

        if ($build === null) {
            return null;
        }

        return new ServerBinary(
            loader: Loader::NeoForge,
            gameVersion: $gameVersion,
            build: $build,
            url: self::MAVEN . "/{$build}/neoforge-{$build}-installer.jar",
            isInstaller: true,
        );
    }

    /**
     * Game version => NeoForge versions, newest first.
     *
     * @return array<string, string[]>
     */
    private function versionsByGame(): array
    {
        return $this->remember('by-game', function (): array {
            $response = Http::timeout(10)->get(self::METADATA);

            if ($response->failed()) {
                return [];
            }

            preg_match_all('#<version>([^<]+)</version>#', $response->body(), $matches);

            $grouped = [];

            foreach (array_reverse($matches[1] ?? []) as $version) {
                // Pre-releases carry a suffix the installer url would not resolve.
                if (str_contains($version, '-')) {
                    continue;
                }

                $game = $this->gameVersionFor($version);

                if ($game === null) {
                    continue;
                }

                $grouped[$game][] = $version;
            }

            return $grouped;
        });
    }

    private function gameVersionFor(string $version): ?string
    {
        $parts = explode('.', $version);
        array_pop($parts);

        // Both schemes write "no patch" as a zero, and Mojang writes it as nothing at
        // all: 21.0.145 is 1.21, not 1.21.0, and 26.2.0.88 is 26.2, not 26.2.0.
        if (count($parts) === 2) {
            return $parts[1] === '0' ? "1.{$parts[0]}" : "1.{$parts[0]}.{$parts[1]}";
        }

        if (count($parts) === 3) {
            return $parts[2] === '0'
                ? "{$parts[0]}.{$parts[1]}"
                : implode('.', $parts);
        }

        return null;
    }
}
