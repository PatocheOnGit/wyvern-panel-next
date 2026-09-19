<?php

use App\Models\Role;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Cache;

/*
 * The two routes phpMyAdmin depends on.
 *
 * /wyvern/internal/pma-authorize is the outer door: nginx calls it on every /pma/app
 * request and forwards only on a 2xx.
 *
 * /wyvern/internal/pma-claim is the handover: phpMyAdmin's signon script exchanges a
 * single-use token for the credentials of one database.
 *
 * They live under tests/Integration rather than tests/Feature because that is the suite
 * phpunit.xml declares and CI runs; tests/Feature is bound by Pest.php and then never
 * executed by anything.
 */

uses(IntegrationTestCase::class);

it('refuses a visitor who is not signed in', function () {
    $this->get('/wyvern/internal/pma-authorize')->assertForbidden();
});

it('lets any signed-in user through the door', function () {
    // Deliberately not root-admin-only. The door only decides who may reach phpMyAdmin at
    // all; which databases they can open is decided by the picker, and enforced after that
    // by the MySQL user's own grants. Gating on root admin would stop ordinary users
    // reaching their own data without protecting anything.
    [$user] = generateTestAccount();

    $this->actingAs($user)
        ->get('/wyvern/internal/pma-authorize')
        ->assertNoContent();
});

it('lets a root admin through too', function () {
    [$admin] = generateTestAccount();
    $admin = $admin->syncRoles(Role::getRootAdmin());

    $this->actingAs($admin)
        ->get('/wyvern/internal/pma-authorize')
        ->assertNoContent();
});

it('never redirects, because nginx would read a redirect as a server error', function () {
    // The reason this route carries no auth middleware. A 302 here turns "please sign in"
    // into a 500 on the page the visitor was trying to open.
    $this->get('/wyvern/internal/pma-authorize')->assertStatus(403);
});

it('hands over the credentials behind a token, once', function () {
    $token = str_repeat('a', 64);

    Cache::put('wyvern:pma:' . $token, [
        'username' => 'u1_example',
        'password' => 'secret',
        'database' => 's1_example',
    ], 60);

    $this->get('/wyvern/internal/pma-claim?token=' . $token)
        ->assertOk()
        ->assertJson([
            'username' => 'u1_example',
            'password' => 'secret',
            'database' => 's1_example',
        ]);

    // Spent by reading it: a token caught in a log or a browser history is worthless.
    $this->get('/wyvern/internal/pma-claim?token=' . $token)->assertNotFound();
});

it('refuses a token that is the wrong shape without touching the cache', function () {
    $this->get('/wyvern/internal/pma-claim?token=short')->assertStatus(400);
    $this->get('/wyvern/internal/pma-claim')->assertStatus(400);
    $this->get('/wyvern/internal/pma-claim?token=' . str_repeat('!', 64))->assertStatus(400);
});

it('refuses a token it has never minted', function () {
    $this->get('/wyvern/internal/pma-claim?token=' . str_repeat('b', 64))->assertNotFound();
});
