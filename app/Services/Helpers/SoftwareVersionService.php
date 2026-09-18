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

    /**
     * Wyvern's own version and the Pelican release under it, in one string.
     *
     * Both numbers, always, because either one alone is a half-answer: ours says what
     * changed, theirs says what it changed on top of.
     */
    public function versionLine(): string
    {
        return trans('wyvern.release.line', [
            'version' => $this->currentPanelVersion(),
            'upstream' => config('wyvern.upstream.project'),
            'upstreamVersion' => config('wyvern.upstream.version'),
        ]);
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

            // The releases list, not /releases/latest.
            //
            // GitHub's "latest" endpoint excludes prereleases, and every 0.x Wyvern tag is
            // marked as one — correctly, since a 0.x private panel is not a stable
            // release. Asking for "latest" therefore returned nothing at all and the
            // dashboard reported that it could not check. The list is ordered newest
            // first and includes prereleases, which is the question actually being asked:
            // what is the most recent release of this repository.
            $releases = $request
                ->get('https://api.github.com/repos/' . $this->updateRepository() . '/releases', ['per_page' => 1])
                ->throw()
                ->json();

            return is_array($releases) ? ($releases[0] ?? null) : null;
        } catch (Exception) {
            return null;
        }
    }

    public function wingsRepository(): string
    {
        return (string) config('wyvern.updates.wings_repository');
    }

    public function latestWingsVersion(): string
    {
        $key = 'wings:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                // The list rather than /releases/latest, for the same reason as the panel's
                // own check: "latest" hides prereleases, and every tag carrying "beta" is
                // marked as one by the release workflow. The list is newest-first and
                // answers the question actually being asked.
                $releases = Http::timeout(5)
                    ->connectTimeout(1)
                    ->get('https://api.github.com/repos/' . $this->wingsRepository() . '/releases', ['per_page' => 1])
                    ->throw()
                    ->json();

                $tag = is_array($releases) ? ($releases[0]['tag_name'] ?? null) : null;

                return filled($tag) ? ltrim((string) $tag, 'v') : 'error';
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

        $latest = $this->latestWingsVersion();

        // Say nothing rather than something wrong. When the check itself failed — GitHub
        // unreachable, or the anonymous 60/hour rate limit spent — the sentinel would be
        // fed to version_compare() and quietly rank below any real version, so every node
        // would report as current. A failed check is not evidence that a node is current,
        // but it is not evidence of the opposite either, and a health check that cries wolf
        // whenever the network hiccups gets ignored.
        if ($latest === 'error') {
            return true;
        }

        return version_compare($version, $latest) >= 0;
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
