<?php

namespace Wyvern\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Tells phpMyAdmin which database the visitor chose, and as whom to open it.
 *
 * phpMyAdmin's `signon` auth calls a PHP script to ask who it should log in as, and it
 * asks on **every** request — page loads, AJAX calls, the lot. That is the fact this whole
 * design turns on. The first version handed over a single-use token, which worked exactly
 * once: the first page rendered, and the next click bounced back to the picker because the
 * token had been spent. A signon source has to be readable for as long as the session
 * lasts, not consumed by reading it.
 *
 * So the selection lives against the panel session instead. The picker writes it, this
 * route reads it, and phpMyAdmin's script forwards the visitor's cookies so that the panel
 * resolves the same user it would for any other request. The panel session *is* the
 * single-sign-on session, which is what signon was built to expect.
 *
 * The trust boundary is therefore the panel session itself, and nothing weaker: to get
 * these credentials you must already hold a session that chose this database and proved
 * its password. That is not a new key to the database — whoever holds that session can
 * read the same password off the server's Databases page anyway.
 *
 * The loopback check stays. It costs nothing and removes the class of mistake where a
 * proxy or a future port-forward quietly publishes this route.
 */
class ClaimPhpMyAdminSignon
{
    /**
     * Where a visitor's current selection lives.
     */
    public static function cacheKey(int $userId): string
    {
        return 'wyvern:pma:user:' . $userId;
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (!in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return response()->json(['error' => 'not local'], 403);
        }

        $user = user();

        if ($user === null) {
            return response()->json(['error' => 'not signed in'], 403);
        }

        $selection = Cache::get(self::cacheKey($user->id));

        // No selection is the normal state for someone who typed /pma/app directly, and it
        // is not an error: phpMyAdmin reads the 404 as "no credentials" and sends them to
        // SignonURL, which is the picker.
        if (!is_array($selection)) {
            return response()->json(['error' => 'nothing chosen'], 404);
        }

        return response()->json([
            'username' => $selection['username'],
            'password' => $selection['password'],
            'database' => $selection['database'],
        ]);
    }
}
