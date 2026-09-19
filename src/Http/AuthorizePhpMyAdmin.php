<?php

namespace Wyvern\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Answers nginx's `auth_request` for /pma.
 *
 * phpMyAdmin has no idea who is signed in to the panel, and giving it a password of its
 * own would mean one more credential to store, rotate and leak. So nginx asks the panel
 * instead: on every request to /pma it replays the visitor's cookies against this route,
 * and forwards the request only if the answer is 2xx.
 *
 * Two constraints come from how nginx reads that answer, and both matter:
 *
 *  - It must never redirect. nginx treats 401 and 403 as "denied" and anything else that
 *    is not 2xx — a 302 to the login page included — as a server error, which surfaces as
 *    a 500 on a page that was merely asking someone to sign in. That is why this route
 *    carries no auth middleware and decides for itself.
 *  - It must say nothing. The body is discarded by nginx, and an endpoint that reports who
 *    someone is would be readable by anyone who can reach it.
 *
 * Being signed in is the whole test, and that is a deliberate loosening from the root-admin
 * check this started as. Back then /pma opened on phpMyAdmin's own login form, where any
 * MySQL account on the host was fair game, so only an administrator could be let near it.
 * Now the way in is Wyvern's database picker: it offers a visitor only the databases the
 * panel already says they may see, and hands phpMyAdmin a session logged in as that one
 * database's MySQL user. The privileges of that user are the real boundary, and MySQL
 * enforces them whatever this route says. Keeping the gate at root-admin would no longer
 * protect anything — it would only stop ordinary users reaching their own data.
 */
class AuthorizePhpMyAdmin
{
    public function __invoke(Request $request): Response
    {
        return response()->noContent(user() !== null ? 204 : 403);
    }
}
