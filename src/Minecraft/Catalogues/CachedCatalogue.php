<?php

namespace Wyvern\Minecraft\Catalogues;

use Illuminate\Support\Facades\Cache;

/**
 * Upstream version APIs are public, rate-limited and change a few times a day at most,
 * so every lookup goes through the cache. Keyed by loader so one flavour's entries can
 * be dropped without touching the others.
 */
abstract class CachedCatalogue
{
    protected int $ttlMinutes = 60;

    /** @template T of mixed
     * @param  callable(): T  $resolve
     * @return T
     */
    protected function remember(string $key, callable $resolve): mixed
    {
        return Cache::remember(
            $this->cacheKey($key),
            now()->addMinutes($this->ttlMinutes),
            $resolve,
        );
    }

    public function forget(string $key): void
    {
        Cache::forget($this->cacheKey($key));
    }

    private function cacheKey(string $key): string
    {
        return 'wyvern.mc.' . $this->loader()->value . '.' . $key;
    }
}
