<?php

namespace Wyvern\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Hands phpMyAdmin the credentials behind a handover token, once.
 *
 * phpMyAdmin's `signon` auth calls a PHP script to ask who it should log in as. That
 * script runs inside phpMyAdmin, not inside the panel, so it cannot read the panel's
 * session, its cache or its encryption key. It calls this instead, over the loopback
 * interface, with the token it was given in the URL.
 *
 * What keeps that safe is not the network — loopback is not a permission — but the token:
 * 64 random characters, minted only after someone proved they hold the database password,
 * valid for one minute, and destroyed by the act of reading it. Replaying it a second time
 * gets nothing, and so does guessing.
 *
 * The loopback check is still worth having. It costs nothing and it removes the whole
 * class of mistake where a reverse proxy, a container network or a future port-forward
 * quietly exposes this route to the internet, where a token could at least be brute-forced
 * in the open.
 */
class ClaimPhpMyAdminSignon
{
    public function __invoke(Request $request): JsonResponse
    {
        if (!in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return response()->json(['error' => 'not local'], 403);
        }

        $token = (string) $request->query('token', '');

        // Length-checked before it reaches the cache so a short or empty token cannot turn
        // into a lookup for a key an attacker controls the shape of.
        if (strlen($token) !== 64 || !ctype_alnum($token)) {
            return response()->json(['error' => 'bad token'], 400);
        }

        // pull() is get-and-forget: one read and the token stops existing, so a token
        // caught in a log cannot be used behind the person it was minted for.
        $payload = Cache::pull('wyvern:pma:' . $token);

        if (!is_array($payload)) {
            return response()->json(['error' => 'unknown or spent token'], 404);
        }

        return response()->json([
            'username' => $payload['username'],
            'password' => $payload['password'],
            'database' => $payload['database'],
        ]);
    }
}
