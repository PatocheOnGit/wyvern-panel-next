<?php

return [

    'driver' => env('SESSION_DRIVER', 'file'),

    'cookie' => env('SESSION_COOKIE', 'wyvern_session'),

    /*
     * Sessions get their own redis database.
     *
     * Laravel's default for this key is null, which sends the session handler to the
     * `default` redis connection — database 0, the same one the cache uses. Laravel's redis
     * cache flush is a FLUSHDB, so `cache:clear`, and therefore `optimize:clear`, signed
     * every user out, including whoever ran it. It cost a session mid-work before it was
     * understood.
     *
     * `config/database.php` already defines a `sessions` connection on database 1 —
     * upstream defines it and then nothing points at it. This points at it.
     */
    'connection' => env('SESSION_CONNECTION', 'sessions'),

];
