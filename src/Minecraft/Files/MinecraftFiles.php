<?php

namespace Wyvern\Minecraft\Files;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Contracts\Filesystem\FileNotFoundException;

/** Reads and writes a Minecraft server's own files through Wings. */
class MinecraftFiles
{
    public const PROPERTIES = '/server.properties';

    public function __construct(private readonly DaemonFileRepository $files) {}

    /** Null when the file does not exist yet. */
    public function read(Server $server, string $path): ?string
    {
        try {
            return (string) $this->files->setServer($server)->getContent($path, 4 * 1024 * 1024);
        } catch (FileNotFoundException) {
            return null;
        }
    }

    public function write(Server $server, string $path, string $content): void
    {
        $this->files->setServer($server)->putContent($path, $content);
    }

    /** @return list<array<string, mixed>> */
    public function readJsonList(Server $server, string $path): array
    {
        $data = json_decode($this->read($server, $path) ?? '[]', true);

        return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
    }

    /** @param list<array<string, mixed>> $entries */
    public function writeJsonList(Server $server, string $path, array $entries): void
    {
        $json = (string) json_encode(array_values($entries), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // Halve PHP's four-space indent to the two spaces the server itself writes.
        $this->write($server, $path, preg_replace_callback('/^ +/m', fn (array $m) => str_repeat(' ', intdiv(strlen($m[0]), 2)), $json) . "\n");
    }

    public function properties(Server $server): ?ServerProperties
    {
        $content = $this->read($server, self::PROPERTIES);

        return $content === null ? null : ServerProperties::parse($content);
    }

    public function saveProperties(Server $server, ServerProperties $properties): void
    {
        $this->write($server, self::PROPERTIES, $properties->render());
    }
}
