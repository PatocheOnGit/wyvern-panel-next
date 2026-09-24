<?php

namespace Wyvern\FiveM;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/** Cfx.re's Linux server artifacts: the four legacy channels, every legacy build, and Enhanced. */
class Artifacts
{
    private const CHANGELOG = 'https://changelogs-live.fivem.net/api/changelog/versions/linux/server';

    private const LISTING = 'https://runtime.fivem.net/artifacts/fivem/build_proot_linux/master/';

    private const DOWNLOADS = 'https://docs.fivem.net/docs/server-download/';

    public const CHANNELS = ['recommended', 'optional', 'latest', 'critical'];

    /** @return array<string, array{build: string, txadmin: ?string}> */
    public function channels(): array
    {
        return Cache::remember('wyvern.fivem.channels', now()->addMinutes(30), function (): array {
            $data = Http::timeout(10)->get(self::CHANGELOG)->json() ?? [];
            $channels = [];

            foreach (self::CHANNELS as $channel) {
                if (isset($data[$channel])) {
                    $channels[$channel] = ['build' => (string) $data[$channel], 'txadmin' => $data["{$channel}_txadmin"] ?? null];
                }
            }

            return $channels;
        });
    }

    /**
     * The one FiveM Enhanced build Cfx.re publishes, read from its download page as the egg does.
     *
     * @return array{build: string, url: string}|null
     */
    public function enhanced(): ?array
    {
        $data = Cache::remember('wyvern.fivem.enhanced', now()->addMinutes(30), function (): array {
            $html = Http::timeout(10)->get(self::DOWNLOADS)->body();

            if (!preg_match('#<script id="__NEXT_DATA__"[^>]*>(.*?)</script>#s', $html, $m)) {
                return [];
            }

            $linux = json_decode($m[1], true)['props']['pageProps']['enhanced']['linux'][0] ?? null;

            return is_array($linux) && isset($linux['downloadURL'])
                ? ['build' => (string) preg_replace('/\D+/', '', (string) ($linux['subtitle'] ?? '')), 'url' => (string) $linux['downloadURL']]
                : [];
        });

        return $data === [] ? null : $data;
    }

    /** @return list<string> "35945-0d8a2a…", newest first */
    public function builds(int $limit = 150): array
    {
        return Cache::remember('wyvern.fivem.builds', now()->addMinutes(30), function () use ($limit): array {
            preg_match_all('#\./(\d+-[0-9a-f]+)/fx\.tar\.xz#', Http::timeout(15)->get(self::LISTING)->body(), $m);
            $builds = array_values(array_unique($m[1]));
            usort($builds, fn ($a, $b) => (int) $b <=> (int) $a);

            return array_slice($builds, 0, $limit);
        });
    }
}
