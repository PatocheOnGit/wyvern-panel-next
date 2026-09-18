<?php

namespace Wyvern\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Answers nginx's `auth_request` for /pma.
 *
 * phpMyAdmin has no idea who is signed in to the panel, and giving it a second password
 * would mean one more credential to store, rotate and leak. So nginx asks the panel
 * instead: on every request to /pma it replays the visitor's cookies against this route,
 * and forwards the request only if the answer is 2xx.
 *
 * Two rules follow from how nginx reads that answer, and both matter:
 *
 *  - It must never redirect. nginx treats 401 and 403 as "denied" and anything else that
 *    is not 2xx — a 302 to the login page included — as a server error, which surfaces as
 *    a 500 on a page that was merely asking someone to sign in. That is why this route
 *    carries no auth middleware and decides for itself.
 *  - It must say nothing. The body is discarded by nginx, and an endpoint that reports who
 *    someone is would be readable by anyone who can reach it.
 *
 * Root admin, not merely "can view databases": whoever gets here holds every database on
 * the host, including the panel's own, so the gate is the strongest role the panel has.
 */
class AuthorizePhpMyAdmin
{
    public function __invoke(Request $request): Response
    {
        return response()->noContent(user()?->isRootAdmin() ? 204 : 403);
    }
}
