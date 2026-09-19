<?php

use App\Models\Role;
use App\Tests\Integration\IntegrationTestCase;
use Illuminate\Support\Facades\Cache;
use Wyvern\Http\ClaimPhpMyAdminSignon;

/*
 * The two routes phpMyAdmin depends on.
 *
 * /wyvern/internal/pma-authorize is the outer door: nginx calls it on every /pma/app
 * request and forwards only on a 2xx.
 *
 * /wyvern/internal/pma-claim is the signon source: phpMyAdmin's script asks it, on every
 * request it serves, which database the visitor chose and as whom to open it.
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
    // all; which database they get is decided by the picker, and enforced after that by
    // the MySQL user's own grants. Gating on root admin would stop ordinary users reaching
    // their own data without protecting anything.
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

it('tells phpMyAdmin which database the visitor chose', function () {
    [$user] = generateTestAccount();

    Cache::put(ClaimPhpMyAdminSignon::cacheKey($user->id), [
        'username' => 'u1_example',
        'password' => 'secret',
        'database' => 's1_example',
    ], 60);

    $this->actingAs($user)
        ->get('/wyvern/internal/pma-claim')
        ->assertOk()
        ->assertJson([
            'username' => 'u1_example',
            'password' => 'secret',
            'database' => 's1_example',
        ]);
});

it('answers the same thing every time, because signon is asked on every request', function () {
    // The bug this replaced: a single-use token rendered the first page and then threw the
    // visitor back to the picker on their first click, because phpMyAdmin re-reads its
    // signon source for every request including AJAX.
    [$user] = generateTestAccount();

    Cache::put(ClaimPhpMyAdminSignon::cacheKey($user->id), [
        'username' => 'u1_example',
        'password' => 'secret',
        'database' => 's1_example',
    ], 60);

    foreach (range(1, 3) as $ignored) {
        $this->actingAs($user)
            ->get('/wyvern/internal/pma-claim')
            ->assertOk()
            ->assertJsonPath('username', 'u1_example');
    }
});

it('gives one visitor nothing of another visitor', function () {
    [$chooser] = generateTestAccount();
    [$stranger] = generateTestAccount();

    Cache::put(ClaimPhpMyAdminSignon::cacheKey($chooser->id), [
        'username' => 'u1_example',
        'password' => 'secret',
        'database' => 's1_example',
    ], 60);

    $this->actingAs($stranger)
        ->get('/wyvern/internal/pma-claim')
        ->assertNotFound();
});

it('refuses a caller with no panel session at all', function () {
    $this->get('/wyvern/internal/pma-claim')->assertForbidden();
});

it('answers 404, not an error, when nothing has been chosen yet', function () {
    // phpMyAdmin reads this as "no credentials" and sends the visitor to the picker, which
    // is exactly where someone who typed /pma/app directly should end up.
    [$user] = generateTestAccount();

    $this->actingAs($user)
        ->get('/wyvern/internal/pma-claim')
        ->assertNotFound();
});
