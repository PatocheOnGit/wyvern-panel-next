<?php

namespace Wyvern\Content;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Http\Client\ConnectionException;
use RuntimeException;
use Wyvern\Content\Sources\ModrinthSource;
use Wyvern\Minecraft\InstallRecords;

/**
 * Puts a file from a library onto a server.
 *
 * The download happens on the node, not here: Wings' files/pull fetches the url
 * directly, so a 60 MiB mod never travels through the panel. That is also why install
 * is fast enough to do from a click.
 */
class ContentInstaller
{
    private const MAX_DEPTH = 3;

    public function __construct(
        private readonly DaemonFileRepository $files,
        private readonly InstalledContent $installed,
        private readonly ModrinthSource $modrinth,
        private readonly InstallRecords $records,
    ) {}

    /**
     * The file, then every required dependency that is not installed yet.
     *
     * @return list<string> paths installed, the file first
     *
     * @throws RuntimeException
     * @throws ConnectionException
     */
    public function installWithDependencies(Server $server, ContentFile $file, ContentType $type): array
    {
        $profile = ServerProfile::of($server);
        $present = array_filter(array_column($this->installed->read($server)['files'], 'project'));
        $seen = array_flip(array_filter([...$present, $file->projectId]));

        $paths = [$this->install($server, $file, $type)];
        $queue = array_map(fn ($d) => [$d, 1], $file->dependencies);

        while ($queue !== []) {
            [$dependency, $depth] = array_shift($queue);
            $project = $dependency['project'];

            if (($project !== null && isset($seen[$project])) || $depth > self::MAX_DEPTH) {
                continue;
            }

            $resolved = $dependency['version'] !== null
                ? $this->modrinth->version($dependency['version'])
                : collect($this->modrinth->files($project, $profile->loader, $profile->gameVersion($this->records)))
                    ->first(fn (ContentFile $f) => $f->isDownloadable());

            if ($resolved === null || isset($seen[(string) $resolved->projectId])) {
                continue;
            }

            $seen[(string) $resolved->projectId] = true;
            // One at a time: Wings refuses a fourth concurrent download per server.
            $paths[] = $this->install($server, $resolved, $type, foreground: true);
            $queue = [...$queue, ...array_map(fn ($d) => [$d, $depth + 1], $resolved->dependencies)];
        }

        return $paths;
    }

    /**
     * @throws RuntimeException when the file cannot be installed on this server
     * @throws ConnectionException when the node cannot be reached
     */
    public function install(Server $server, ContentFile $file, ContentType $type, bool $foreground = false): string
    {
        if (!$file->isDownloadable()) {
            throw new RuntimeException(trans('wyvern.content.errors.not_distributable'));
        }

        $profile = ServerProfile::of($server);

        if (!$profile->isKnown()) {
            throw new RuntimeException(trans('wyvern.content.errors.unknown_loader'));
        }

        if (!in_array($type, $profile->installableTypes(), true)) {
            throw new RuntimeException(trans('wyvern.content.errors.wrong_type', [
                'loader' => $profile->loader->label(),
            ]));
        }

        $directory = $type->directoryFor($profile->loader);

        $this->files->setServer($server)->pull($file->url, '/' . $directory, [
            'filename' => $file->filename,
            // Background: a click should not hold a request open for a 60 MiB download.
            'foreground' => $foreground,
        ]);

        $path = trim($directory . '/' . $file->filename, '/');
        $this->installed->record($server, [$path => self::record($file)]);

        return $path;
    }

    /** @return array<string, mixed> */
    public static function record(ContentFile $file): array
    {
        return [
            'source' => $file->source,
            'project' => $file->projectId,
            'version' => $file->id,
            'version_name' => $file->versionName,
            'sha1' => $file->sha1,
        ];
    }
}
