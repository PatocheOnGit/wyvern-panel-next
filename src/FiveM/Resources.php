<?php

namespace Wyvern\FiveM;

use App\Repositories\Daemon\DaemonFileRepository;

/** The resources in the game server's resources/, found by their manifest files in one Wings search each. */
class Resources
{
    public function __construct(private readonly DaemonFileRepository $files) {}

    /**
     * @param  list<string>  $ensured  names and [categories] from server.cfg
     * @return list<array{name: string, path: string, category: ?string, ensured: bool, via: ?string, origin: string}>
     */
    public function list(FiveMServer $fivem, Layout $layout, array $ensured): array
    {
        $server = $fivem->server;
        $repo = $this->files->setServer($server);
        $base = $layout->resources();
        $found = [];

        foreach (['fxmanifest.lua', '__resource.lua'] as $manifest) {
            try {
                $entries = $repo->search($manifest, $base);
            } catch (\Throwable) {
                $entries = [];
            }

            foreach (is_array($entries) ? $entries : [] as $entry) {
                $path = dirname('/' . ltrim((string) ($entry['name'] ?? ''), '/'));
                if (basename((string) ($entry['name'] ?? '')) === $manifest && str_starts_with($path, $base . '/')) {
                    $found[$path] = true;
                }
            }
        }

        $lower = array_map('strtolower', $ensured);
        $rows = [];

        foreach (array_keys($found) as $path) {
            $name = basename($path);
            $categories = array_values(array_filter(explode('/', dirname(substr($path, strlen($base)))), fn ($p) => str_starts_with($p, '[')));
            // "ensure [gameplay]" starts every resource under that category.
            $via = collect($categories)->first(fn ($c) => in_array(strtolower($c), $lower, true));

            $rows[] = [
                'name' => $name,
                'path' => $path,
                'category' => $categories === [] ? null : implode(' / ', $categories),
                'ensured' => in_array(strtolower($name), $lower, true) || $via !== null,
                'via' => in_array(strtolower($name), $lower, true) ? null : $via,
                'origin' => 'folder',
            ];
        }

        usort($rows, fn ($a, $b) => [$a['category'] ?? '', $a['name']] <=> [$b['category'] ?? '', $b['name']]);

        // Ensured but not in resources/: shipped inside the artifact, or simply missing.
        $present = array_map('strtolower', array_column($rows, 'name'));
        $builtin = $this->systemResources($fivem);

        foreach ($ensured as $name) {
            // The console bridge the egg adds under txAdmin; stopping it would cut the console off.
            if (strcasecmp($name, 'wyvern-console') === 0) {
                continue;
            }

            if (!str_starts_with($name, '[') && !in_array(strtolower($name), $present, true)) {
                $rows[] = [
                    'name' => $name,
                    'path' => '',
                    'category' => null,
                    'ensured' => true,
                    'via' => null,
                    'origin' => in_array(strtolower($name), $builtin, true) ? 'builtin' : 'missing',
                ];
            }
        }

        return $rows;
    }

    /** @return list<string> resources the artifact ships in its system_resources */
    private function systemResources(FiveMServer $fivem): array
    {
        $dir = $fivem->enhanced() ? '/alpine/opt/cfx-server/system_resources' : '/alpine/opt/cfx-server/citizen/system_resources';

        try {
            $entries = $this->files->setServer($fivem->server)->getDirectory($dir);
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_map(fn ($e) => strtolower((string) ($e['name'] ?? '')), $entries));
    }
}
