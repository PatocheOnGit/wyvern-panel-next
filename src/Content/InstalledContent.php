<?php

namespace Wyvern\Content;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Wyvern\Minecraft\Files\MinecraftFiles;

/**
 * What the panel installed, kept in the server's own .wyvern/content.json.
 *
 * Paths are stored without a ".disabled" suffix, so switching a file off and on again
 * does not lose its record.
 */
class InstalledContent
{
    public const PATH = '/.wyvern/content.json';

    public function __construct(
        private readonly MinecraftFiles $files,
        private readonly DaemonFileRepository $daemon,
    ) {}

    /** @return array{files: array<string, array<string, mixed>>, modpack: array<string, mixed>|null} */
    public function read(Server $server): array
    {
        try {
            $data = json_decode($this->files->read($server, self::PATH) ?? '', true);
        } catch (\Throwable) {
            $data = null;
        }

        return [
            'files' => is_array($data['files'] ?? null) ? $data['files'] : [],
            'modpack' => is_array($data['modpack'] ?? null) ? $data['modpack'] : null,
        ];
    }

    /** @param array<string, array<string, mixed>> $entries path => record */
    public function record(Server $server, array $entries): void
    {
        $data = $this->read($server);

        foreach ($entries as $path => $entry) {
            $data['files'][self::key($path)] = $entry;
        }

        $this->write($server, $data);
    }

    public function forget(Server $server, string $path): void
    {
        $data = $this->read($server);
        unset($data['files'][self::key($path)]);

        $this->write($server, $data);
    }

    /** Drops every record under a directory, when the directory itself was cleared. */
    public function forgetDirectory(Server $server, string $directory): void
    {
        $data = $this->read($server);
        $prefix = trim($directory, '/') . '/';

        $data['files'] = array_filter($data['files'], fn (string $path) => !str_starts_with($path, $prefix), ARRAY_FILTER_USE_KEY);

        $this->write($server, $data);
    }

    /** @param array<string, mixed>|null $modpack */
    public function setModpack(Server $server, ?array $modpack): void
    {
        $data = $this->read($server);
        $data['modpack'] = $modpack;

        $this->write($server, $data);
    }

    public static function key(string $path): string
    {
        return preg_replace('/\.disabled$/', '', trim($path, '/')) ?? $path;
    }

    /** @param array{files: array<string, mixed>, modpack: mixed} $data */
    private function write(Server $server, array $data): void
    {
        $json = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        try {
            $this->files->write($server, self::PATH, $json);
        } catch (\Throwable) {
            // Older Wings do not create the parent directory on write.
            $this->daemon->setServer($server)->createDirectory('.wyvern', '/');
            $this->files->write($server, self::PATH, $json);
        }
    }
}
