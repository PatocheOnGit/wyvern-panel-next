<?php

namespace Wyvern\FiveM;

use Wyvern\Minecraft\Files\MinecraftFiles;

/** Where the game server's server.cfg and resources/ are: the server root, or txAdmin's deployment. */
final class Layout
{
    private const HOME = '/home/container';

    /** @param string $root '' for the server root, else "/txData/…" */
    private function __construct(public readonly string $root, public readonly string $cfg, public readonly bool $pending) {}

    public static function of(FiveMServer $fivem, MinecraftFiles $files): self
    {
        if (!$fivem->usesTxAdmin()) {
            return new self('', FiveMServer::CFG, false);
        }

        $data = rtrim($fivem->env['TXHOST_DATA_PATH'] ?? '', '/') ?: self::HOME . '/txData';

        try {
            $profile = json_decode($files->read($fivem->server, self::relative($data) . '/default/config.json') ?? '', true);
        } catch (\Throwable) {
            $profile = null;
        }

        $dataPath = is_array($profile) ? ($profile['server']['dataPath'] ?? null) : null;

        // txAdmin writes its deployment path only once its setup is done.
        if (!is_string($dataPath) || $dataPath === '') {
            return new self('', FiveMServer::CFG, true);
        }

        $root = self::relative(rtrim($dataPath, '/'));
        $cfg = (string) ($profile['server']['cfgPath'] ?? 'server.cfg');

        return new self($root, str_starts_with($cfg, '/') ? self::relative($cfg) : $root . '/' . $cfg, false);
    }

    public function resources(): string
    {
        return $this->root . '/resources';
    }

    private static function relative(string $path): string
    {
        return str_starts_with($path, self::HOME) ? substr($path, strlen(self::HOME)) : $path;
    }
}
