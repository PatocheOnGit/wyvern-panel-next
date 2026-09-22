<?php

namespace Wyvern\Minecraft;

use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Illuminate\Support\Facades\Cache;

/** Reads .wyvern/install.json through Wings, cached per server. */
class InstallRecords
{
    public const PATH = '/.wyvern/install.json';

    public function __construct(private readonly DaemonFileRepository $files) {}

    public function of(Server $server): ?InstallRecord
    {
        $key = self::cacheKey($server);
        $data = Cache::get($key);

        if (!is_array($data)) {
            $data = $this->read($server);
            // Missing file or unreachable node: retry soon rather than in an hour.
            Cache::put($key, $data, $data === [] ? now()->addMinute() : now()->addHour());
        }

        return $data === [] ? null : InstallRecord::fromArray($data);
    }

    public static function forget(Server $server): void
    {
        Cache::forget(self::cacheKey($server));
    }

    /** @return array<string, mixed> */
    private function read(Server $server): array
    {
        try {
            $data = json_decode($this->files->setServer($server)->getContent(self::PATH, 4096), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }

    private static function cacheKey(Server $server): string
    {
        return 'wyvern.install.' . $server->uuid;
    }
}
