<?php

namespace Wyvern\Content\Modpack;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Closure;
use RuntimeException;
use Wyvern\Content\InstalledContent;
use Wyvern\Content\ServerProfile;
use Wyvern\Content\Sources\ModrinthSource;
use Wyvern\Minecraft\InstallRecords;

/**
 * Installs a Modrinth modpack onto a server.
 *
 * A .mrpack is not a server: it is a zip holding an index of files to fetch and an
 * overrides tree to lay on top. So "press install and it runs" is a sequence, and every
 * step of it happens on the node — the panel only tells Wings what to do next, and
 * waits. No modpack is ever downloaded through the panel.
 *
 *   1. pull the archive into a staging directory
 *   2. decompress it there
 *   3. read modrinth.index.json
 *   4. pull every file the index lists that is not client-only
 *   5. lay overrides/ over the server, then server-overrides/ on top
 *   6. remove the staging directory
 *
 * Wings' pull is asynchronous, so each stage waits on the filesystem rather than on a
 * response. That is more work than a synchronous download and it is the only way a
 * 350 MiB pack does not hold an HTTP request open for ten minutes.
 */
class ModpackInstaller
{
    private const STAGING = '.wyvern-modpack';

    /**
     * Wings refuses a fourth concurrent remote download per server and answers 400
     * rather than queueing. The limit is hardcoded in the daemon, so this matches it
     * instead of being configured around it.
     */
    private const MAX_CONCURRENT_PULLS = 3;

    public function __construct(
        private readonly DaemonFileRepository $files,
        private readonly ModrinthSource $modrinth,
        private readonly InstallRecords $records,
        private readonly InstalledContent $installed,
    ) {}

    /**
     * @param  Closure(string): void|null  $progress
     * @param  array{project?: ?string, version?: ?string}  $pack
     *
     * @throws RuntimeException
     */
    public function install(Server $server, string $archiveUrl, ?Closure $progress = null, array $pack = []): ModpackIndex
    {
        $index = $this->prepare($server, $archiveUrl, $progress);

        try {
            $this->assertFits($server, $index);
        } catch (RuntimeException $e) {
            $this->discard($server);

            throw $e;
        }

        $this->apply($server, $index, $progress, $pack);

        return $index;
    }

    /**
     * Download and unpack the pack into staging, and read its index. A server switch can
     * happen between this and apply(): the install script leaves staging alone.
     *
     * @param  Closure(string): void|null  $progress
     *
     * @throws RuntimeException
     */
    public function prepare(Server $server, string $archiveUrl, ?Closure $progress = null): ModpackIndex
    {
        $repo = $this->files->setServer($server);
        $say = $progress ?? fn (string $m) => null;

        try {
            $say('preparing');
            $this->reset($repo);
            $repo->createDirectory(self::STAGING, '/');

            // Named .zip rather than .mrpack so the daemon's archive detection has
            // nothing to work out; the bytes are a zip either way.
            $say('downloading the pack');
            $repo->pull($archiveUrl, '/' . self::STAGING, ['filename' => 'pack.zip']);
            $this->waitForFile($repo, '/' . self::STAGING, 'pack.zip', $this->timeout());

            $say('unpacking');
            $repo->decompressFile('/' . self::STAGING, 'pack.zip');
            $this->waitForFile($repo, '/' . self::STAGING, 'modrinth.index.json', 300);

            return ModpackIndex::parse(
                $repo->getContent('/' . self::STAGING . '/modrinth.index.json', 8 * 1024 * 1024)
            );
        } catch (\Throwable $e) {
            $this->reset($repo);

            throw $e instanceof RuntimeException ? $e : new RuntimeException($e->getMessage(), 0, $e);
        }
    }

    public function discard(Server $server): void
    {
        $this->reset($this->files->setServer($server));
    }

    /**
     * Fetch the pack's files and lay its overrides, from what prepare() staged.
     *
     * @param  Closure(string): void|null  $progress
     * @param  array{project?: ?string, version?: ?string}  $pack
     */
    public function apply(Server $server, ModpackIndex $index, ?Closure $progress = null, array $pack = [], bool $replaceMods = false): void
    {
        $repo = $this->files->setServer($server);
        $say = $progress ?? fn (string $m) => null;

        try {
            if ($replaceMods) {
                $say('removing the previous mods');
                $this->deleteQuietly($repo, 'mods');
                $this->installed->forgetDirectory($server, 'mods');
            }

            $wanted = $this->serverSafeFiles($index);
            $skipped = count($index->files) - count($wanted);
            $total = count($wanted);

            $say(sprintf(
                '%s: %d files to fetch%s',
                $index->name,
                $total,
                $skipped > 0 ? ", {$skipped} client-only skipped" : '',
            ));

            $done = 0;

            foreach (array_chunk($wanted, self::MAX_CONCURRENT_PULLS) as $batch) {
                foreach ($batch as $file) {
                    $repo->pull($file->url, $file->directory(), ['filename' => $file->filename()]);
                }

                $this->waitForBatch($repo, $batch);
                $done += count($batch);
                $say("fetched {$done}/{$total}");
            }

            $say('applying overrides');
            $this->applyOverrides($repo, 'overrides');
            $this->applyOverrides($repo, 'server-overrides');

            $this->installed->record($server, collect($wanted)->mapWithKeys(fn (ModpackFile $file) => [$file->path => [
                'source' => 'modrinth',
                'project' => $file->projectId(),
                'version' => $file->versionId(),
                'sha1' => $file->sha1,
                'modpack' => true,
            ]])->all());

            $this->installed->setModpack($server, [
                'source' => 'modrinth',
                'project' => $pack['project'] ?? null,
                'version' => $pack['version'] ?? null,
                'name' => $index->name,
                'version_name' => $index->versionId,
            ]);
        } finally {
            // Whatever happened, a staging tree is not something to leave behind in
            // someone's server directory.
            $say('cleaning up');
            $this->reset($repo);
        }

        $say('done');
    }

    private function deleteQuietly(DaemonFileRepository $repo, string $path): void
    {
        try {
            $repo->deleteFiles('/', [$path]);
        } catch (\Throwable) {
            // Nothing there.
        }
    }

    /**
     * The files that will actually run here.
     *
     * The index states this per file and pack authors get it wrong: the pack tested
     * against declared Sodium — a client renderer — as server-required, and installing
     * it stopped the server booting at all. So the index is filtered first, then each
     * remaining file is checked against what its project says on Modrinth, and anything
     * either source calls unsupported is left out.
     *
     * @return ModpackFile[]
     */
    private function serverSafeFiles(ModpackIndex $index): array
    {
        $candidates = $index->serverFiles();

        $support = $this->modrinth->serverSupport(
            array_map(fn (ModpackFile $f): ?string => $f->projectId(), $candidates)
        );

        return array_values(array_filter($candidates, function (ModpackFile $file) use ($support): bool {
            $project = $file->projectId();

            // Not a Modrinth CDN url, so there is nothing to cross-check against;
            // the index's own word stands.
            if ($project === null || !isset($support[$project])) {
                return true;
            }

            return $support[$project] !== 'unsupported';
        }));
    }

    /** @throws RuntimeException when the pack targets another loader or Minecraft version */
    public function assertFits(Server $server, ModpackIndex $index): void
    {
        $profile = ServerProfile::of($server);
        $packLoader = $index->loader();

        if ($packLoader !== null && $profile->loader !== null && $packLoader !== $profile->loader->value) {
            throw new RuntimeException(trans('wyvern.content.errors.modpack_loader', [
                'pack' => $index->name,
                'expected' => $packLoader,
                'actual' => $profile->loader->label(),
            ]));
        }

        $packVersion = $index->minecraftVersion();
        $serverVersion = $profile->gameVersion($this->records);

        // An unknown server version cannot be compared; the search already narrowed it.
        if ($packVersion !== null && $serverVersion !== null && $packVersion !== $serverVersion) {
            throw new RuntimeException(trans('wyvern.content.errors.modpack_version', [
                'pack' => $index->name,
                'expected' => $packVersion,
                'actual' => $serverVersion,
            ]));
        }
    }

    private function timeout(): int
    {
        return (int) config('wyvern.content.modpack_timeout', 900);
    }

    private function reset(DaemonFileRepository $repo): void
    {
        try {
            $repo->deleteFiles('/', [self::STAGING]);
        } catch (\Throwable) {
            // Nothing to remove on a first run.
        }
    }

    /** @throws RuntimeException */
    private function waitForFile(DaemonFileRepository $repo, string $directory, string $name, int $timeoutSeconds): void
    {
        $deadline = time() + $timeoutSeconds;
        $lastSize = -1;

        while (time() < $deadline) {
            $entry = collect($this->listQuietly($repo, $directory))->firstWhere('name', $name);

            if ($entry) {
                $size = (int) ($entry['size'] ?? 0);

                // A file appears the moment the transfer starts, so existence alone
                // proves nothing. Two equal readings mean it stopped growing.
                if ($size > 0 && $size === $lastSize) {
                    return;
                }

                $lastSize = $size;
            }

            sleep(3);
        }

        throw new RuntimeException("Timed out waiting for {$name}.");
    }

    /**
     * @param  ModpackFile[]  $batch
     *
     * @throws RuntimeException
     */
    private function waitForBatch(DaemonFileRepository $repo, array $batch): void
    {
        // A pulled file appears at zero bytes the instant the transfer starts, so
        // presence proves nothing — and treating it as done starts the next batch while
        // Wings is still holding its three slots. The index states each file's size, so
        // that is what completion is measured against.
        $expected = [];

        foreach ($batch as $file) {
            $expected[$file->directory()][$file->filename()] = $file->size;
        }

        $deadline = time() + $this->timeout();
        $previous = [];

        while (time() < $deadline) {
            $pending = 0;

            foreach ($expected as $directory => $files) {
                $there = collect($this->listQuietly($repo, $directory))
                    ->where('file', true)
                    ->keyBy('name');

                foreach ($files as $name => $size) {
                    $entry = $there->get($name);

                    if (!$entry) {
                        $pending++;

                        continue;
                    }

                    $actual = (int) ($entry['size'] ?? 0);
                    $key = $directory . '/' . $name;

                    $complete = $size !== null
                        ? $actual >= $size
                        // No stated size: settle for the reading holding still.
                        : ($actual > 0 && ($previous[$key] ?? -1) === $actual);

                    $previous[$key] = $actual;

                    if (!$complete) {
                        $pending++;
                    }
                }
            }

            if ($pending === 0) {
                return;
            }

            sleep(3);
        }

        throw new RuntimeException("Timed out waiting for the pack's files to download.");
    }

    /**
     * Lay one override tree over the server.
     *
     * Directories are merged rather than replaced: the pack's config/ has to land
     * alongside what the loader already wrote, not instead of it.
     */
    private function applyOverrides(DaemonFileRepository $repo, string $folder): void
    {
        $root = '/' . self::STAGING . '/' . $folder;
        $entries = $this->listQuietly($repo, $root);

        if ($entries === []) {
            return; // the pack does not ship this tree
        }

        $this->mergeInto($repo, $root, '', $entries);
    }

    /** @param array<int, array<string, mixed>> $entries */
    private function mergeInto(DaemonFileRepository $repo, string $from, string $to, array $entries): void
    {
        $existing = collect($this->listQuietly($repo, $to === '' ? '/' : '/' . $to))->keyBy('name');

        foreach ($entries as $entry) {
            $name = $entry['name'];
            $source = $from . '/' . $name;
            $target = trim($to . '/' . $name, '/');
            $clash = $existing->get($name);

            // A directory that already exists cannot be renamed onto itself, so walk
            // into it and merge a level deeper.
            if (($entry['directory'] ?? false) && $clash && ($clash['directory'] ?? false)) {
                $this->mergeInto($repo, $source, $target, $this->listQuietly($repo, $source));

                continue;
            }

            if ($clash) {
                $repo->deleteFiles('/', [$target]);
            }

            $repo->renameFiles('/', [['from' => ltrim($source, '/'), 'to' => $target]]);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function listQuietly(DaemonFileRepository $repo, string $path): array
    {
        try {
            return $repo->getDirectory($path === '' ? '/' : $path);
        } catch (\Throwable) {
            return [];
        }
    }
}
