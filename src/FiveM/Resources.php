<?php

namespace Wyvern\FiveM;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;

/** The resources under resources/, found by their manifest files in one Wings search each. */
class Resources
{
    public function __construct(private readonly DaemonFileRepository $files) {}

    /**
     * @param  list<string>  $ensured  names and [categories] from server.cfg
     * @return list<array{name: string, path: string, category: ?string, ensured: bool, via: ?string, origin: string}>
     */
    public function list(Server $server, array $ensured): array
    {
        $repo = $this->files->setServer($server);
        $found = [];

        foreach (['fxmanifest.lua', '__resource.lua'] as $manifest) {
            try {
                $entries = $repo->search($manifest, '/resources');
            } catch (\Throwable) {
                $entries = [];
            }

            foreach (is_array($entries) ? $entries : [] as $entry) {
                $path = dirname('/' . ltrim((string) ($entry['name'] ?? ''), '/'));
                if (basename((string) ($entry['name'] ?? '')) === $manifest && str_starts_with($path, '/resources/')) {
                    $found[$path] = true;
                }
            }
        }

        $lower = array_map('strtolower', $ensured);
        $rows = [];

        foreach (array_keys($found) as $path) {
            $name = basename($path);
            $categories = array_values(array_filter(explode('/', dirname($path)), fn ($p) => str_starts_with($p, '[')));
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
        $builtin = $this->systemResources($server);

        foreach ($ensured as $name) {
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

    /** @return list<string> resources FXServer ships in citizen/system_resources */
    private function systemResources(Server $server): array
    {
        try {
            $entries = $this->files->setServer($server)->getDirectory('/alpine/opt/cfx-server/citizen/system_resources');
        } catch (\Throwable) {
            return [];
        }

        return array_values(array_map(fn ($e) => strtolower((string) ($e['name'] ?? '')), $entries));
    }
}
