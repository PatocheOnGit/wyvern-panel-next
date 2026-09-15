<?php

namespace Wyvern\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Wyvern\Minecraft\Loader;
use Wyvern\Minecraft\VersionCatalogue;

/**
 * Asks every flavour's upstream API what it publishes and then checks that the download
 * it hands back actually exists.
 *
 * This is how the catalogue is verified: six third-party APIs that change their shapes
 * on their own schedule are not something to trust a unit test's fixtures about.
 */
class CheckMinecraftCatalogue extends Command
{
    protected $signature = 'wyvern:mc:check {--loader= : Only this flavour} {--no-download : Skip the HEAD request}';

    protected $description = 'Resolve a download for every Minecraft flavour and verify it exists';

    public function handle(VersionCatalogue $catalogue): int
    {
        $loaders = $this->option('loader')
            ? [Loader::from($this->option('loader'))]
            : Loader::cases();

        $rows = [];
        $failures = 0;

        foreach ($loaders as $loader) {
            $versions = $catalogue->gameVersions($loader);

            if ($versions === []) {
                $rows[] = [$loader->label(), '—', '—', 'no versions returned'];
                $failures++;

                continue;
            }

            $newest = $versions[0];
            $builds = $catalogue->builds($loader, $newest);
            $binary = $catalogue->binary($loader, $newest);

            if (!$binary) {
                $rows[] = [$loader->label(), $newest, count($versions) . ' versions', 'no binary resolved'];
                $failures++;

                continue;
            }

            $status = $this->option('no-download')
                ? 'not checked'
                : $this->head($binary->url);

            if (!str_starts_with($status, '200')) {
                $failures++;
            }

            $rows[] = [
                $loader->label(),
                $newest . ($binary->build ? " build {$binary->build}" : ''),
                count($versions) . ' versions, ' . count($builds) . ' builds',
                $status . ($binary->isInstaller ? '  (installer)' : ''),
            ];
        }

        $this->table(['Flavour', 'Newest', 'Catalogue', 'Download'], $rows);

        if ($failures > 0) {
            $this->error("{$failures} flavour(s) did not resolve to a working download.");

            return self::FAILURE;
        }

        $this->info('Every flavour resolved to a download that exists.');

        return self::SUCCESS;
    }

    private function head(string $url): string
    {
        try {
            $response = Http::timeout(20)->withOptions(['allow_redirects' => true])->head($url);
            $size = $response->header('Content-Length');

            return $response->status() . ($size ? ' · ' . round(((int) $size) / 1048576, 1) . ' MiB' : '');
        } catch (\Throwable $e) {
            return 'failed: ' . mb_substr($e->getMessage(), 0, 40);
        }
    }
}
