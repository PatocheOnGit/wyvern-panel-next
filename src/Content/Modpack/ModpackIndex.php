<?php

namespace Wyvern\Content\Modpack;

use JsonException;

/**
 * modrinth.index.json, parsed.
 *
 * Format version 1, per Modrinth's published spec. Two rules matter for a server and
 * both are easy to get wrong:
 *
 *   A file whose env.server is "unsupported" is a client mod. Installing it on a server
 *   is how a modpack that works everywhere else fails to boot here.
 *
 *   Overrides layer: overrides/ first, then server-overrides/ on top. client-overrides/
 *   is not ours.
 */
final readonly class ModpackIndex
{
    /** @param array<int, ModpackFile> $files */
    private function __construct(
        public string $name,
        public string $versionId,
        public array $files,
        /** @var array<string, string> */
        public array $dependencies,
    ) {}

    /** @throws JsonException when the archive did not contain a usable index */
    public static function parse(string $json): self
    {
        $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);

        if (($data['formatVersion'] ?? null) !== 1 || ($data['game'] ?? null) !== 'minecraft') {
            throw new JsonException('Not a Minecraft modpack index this panel understands.');
        }

        $files = [];

        foreach ($data['files'] ?? [] as $entry) {
            $file = ModpackFile::fromIndex($entry);

            if ($file !== null) {
                $files[] = $file;
            }
        }

        return new self(
            name: $data['name'] ?? 'Modpack',
            versionId: $data['versionId'] ?? '',
            files: $files,
            dependencies: $data['dependencies'] ?? [],
        );
    }

    /** @return ModpackFile[] */
    public function serverFiles(): array
    {
        return array_values(array_filter(
            $this->files,
            fn (ModpackFile $file): bool => $file->runsOnServer(),
        ));
    }

    public function minecraftVersion(): ?string
    {
        return $this->dependencies['minecraft'] ?? null;
    }

    /** The loader the pack expects, as the key Modrinth uses. */
    public function loader(): ?string
    {
        foreach (['neoforge', 'forge', 'fabric-loader', 'quilt-loader'] as $key) {
            if (isset($this->dependencies[$key])) {
                return str_replace('-loader', '', $key);
            }
        }

        return null;
    }

    /** The exact loader version the pack pins, e.g. 0.16.9 or 21.1.77. */
    public function loaderVersion(): ?string
    {
        foreach (['neoforge', 'forge', 'fabric-loader', 'quilt-loader'] as $key) {
            if (isset($this->dependencies[$key])) {
                return (string) $this->dependencies[$key];
            }
        }

        return null;
    }
}
