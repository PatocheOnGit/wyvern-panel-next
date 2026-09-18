<?php

use App\Models\Role;
use App\Tests\Integration\IntegrationTestCase;

/*
 * The gate in front of phpMyAdmin. nginx calls this route on every /pma request and
 * forwards only on a 2xx, so these four answers are the whole access control.
 *
 * It lives under tests/Integration rather than tests/Feature because that is the suite
 * phpunit.xml declares and CI runs; tests/Feature is bound by Pest.php and then never
 * executed by anything.
 */

uses(IntegrationTestCase::class);

it('refuses a visitor who is not signed in', function () {
    $this->get('/wyvern/internal/pma-authorize')->assertForbidden();
});

it('refuses a signed-in user who is not a root admin', function () {
    [$user] = generateTestAccount();

    $this->actingAs($user)
        ->get('/wyvern/internal/pma-authorize')
        ->assertForbidden();
});

it('allows a root admin', function () {
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
