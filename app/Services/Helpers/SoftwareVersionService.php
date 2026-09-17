<?php

namespace App\Services\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;

class SoftwareVersionService
{
    /** The panel has a release to compare against, and we are on it or past it. */
    public const UPDATE_CURRENT = 'current';

    /** The repository publishes a newer release than this install. */
    public const UPDATE_AVAILABLE = 'available';

    /**
     * The question cannot be answered: a canary build has no release to compare with,
     * the repository publishes none, or GitHub could not be reached — which for a
     * private repository means no WYVERN_UPDATE_TOKEN is set.
     */
    public const UPDATE_UNKNOWN = 'unknown';

    public function latestPanelVersionChangelog(): string
    {
        $key = 'panel:latest_version_changelog';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            $release = $this->latestRelease();

            return $release['body'] ?? 'error';
        });
    }

    public function latestPanelVersion(): string
    {
        $key = 'panel:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            $release = $this->latestRelease();

            return isset($release['tag_name']) ? trim($release['tag_name'], 'v') : 'error';
        });
    }

    /**
     * Which of the three things we actually know.
     *
     * isLatestPanel() answers a boolean, and a boolean cannot carry "I could not find
     * out" — so it returned true for a canary build and the dashboard reported an install
     * it had never checked as up to date.
     */
    public function panelUpdateState(): string
    {
        if (config('app.version') === 'canary') {
            return self::UPDATE_UNKNOWN;
        }

        $latest = $this->latestPanelVersion();

        if ($latest === 'error' || $latest === '') {
            return self::UPDATE_UNKNOWN;
        }

        return version_compare(config('app.version'), $latest) >= 0
            ? self::UPDATE_CURRENT
            : self::UPDATE_AVAILABLE;
    }

    public function updateRepository(): string
    {
        return (string) config('wyvern.updates.repository');
    }

    public function updateRepositoryUrl(): string
    {
        return 'https://github.com/' . $this->updateRepository();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function latestRelease(): ?array
    {
        try {
            $request = Http::timeout(5)->connectTimeout(1);

            // A private repository answers 404 to an anonymous caller, which is
            // indistinguishable from "no releases yet" — so the token is the difference
            // between a real answer and a permanent "could not check".
            if (filled($token = config('wyvern.updates.token'))) {
                $request = $request->withToken($token);
            }

            return $request
                ->get('https://api.github.com/repos/' . $this->updateRepository() . '/releases/latest')
                ->throw()
                ->json();
        } catch (Exception) {
            return null;
        }
    }

    public function latestWingsVersion(): string
    {
        $key = 'wings:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/wings/releases/latest')->throw()->json();

                return trim($response['tag_name'], 'v');
            } catch (Exception) {
                return 'error';
            }
        });
    }

    public function isLatestPanel(): bool
    {
        if (config('app.version') === 'canary') {
            return true;
        }

        return version_compare(config('app.version'), $this->latestPanelVersion()) >= 0;
    }

    public function isLatestWings(string $version): bool
    {
        if ($version === 'develop') {
            return true;
        }

        return version_compare($version, $this->latestWingsVersion()) >= 0;
    }

    public function currentPanelVersion(): string
    {
        return cache()->remember('panel:current_version', now()->addMinutes(5), function () {
            if (file_exists(base_path('.git/HEAD'))) {
                $head = explode(' ', file_get_contents(base_path('.git/HEAD')));

                if (array_key_exists(1, $head)) {
                    $path = base_path('.git/' . trim($head[1]));

                    if (file_exists($path)) {
                        return 'canary (' . substr(file_get_contents($path), 0, 7) . ')';
                    }
                }
            }

            return config('app.version');
        });
    }
}
