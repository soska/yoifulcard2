<?php

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Support\CurrentOrganization;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A user who is owner of one business and employee at a second one, joined
 * in that order.
 *
 * @return array{0: User, 1: Organization, 2: Organization}
 */
function twoBusinessMember(
    MembershipRole $firstRole = MembershipRole::Owner,
    MembershipRole $secondRole = MembershipRole::Employee,
): array {
    $user = User::factory()->create();
    $first = Organization::factory()->withMember($user, $firstRole)->create(['name' => 'Café Uno']);

    test()->travel(1)->minute();
    $second = Organization::factory()->withMember($user, $secondRole)->create(['name' => 'Panadería Dos']);

    return [$user, $first, $second];
}

test('user can switch to another business they belong to', function () {
    [$user, $first, $second] = twoBusinessMember();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganization.id', $first->id)
            ->where('organizations', [
                ['id' => $first->id, 'name' => 'Café Uno', 'role' => 'owner'],
                ['id' => $second->id, 'name' => 'Panadería Dos', 'role' => 'employee'],
            ]));

    $this->actingAs($user)
        ->post(route('organizations.switch', $second))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(CurrentOrganization::SESSION_KEY, $second->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('currentOrganization.id', $second->id)
            ->where('currentOrganization.name', 'Panadería Dos')
            ->where('currentOrganization.role', 'employee'));

    // From the reader, the switch goes back to the scanner.
    $this->actingAs($user)
        ->post(route('organizations.switch', $first), ['reader' => 1])
        ->assertRedirect(route('scan'))
        ->assertSessionHas(CurrentOrganization::SESSION_KEY, $first->id);

    $this->actingAs($user)
        ->get(route('scan'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.id', $first->id));
});

test('switching works while the current business is suspended', function () {
    $user = User::factory()->create();
    Organization::factory()->suspended()->withMember($user, MembershipRole::Owner)->create();
    $this->travel(1)->minute();
    $active = Organization::factory()->withMember($user, MembershipRole::Manager)->create();

    $this->actingAs($user)
        ->post(route('organizations.switch', $active))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas(CurrentOrganization::SESSION_KEY, $active->id);
});

test('switching to a business the user doesn\'t belong to returns 403', function () {
    [$user, $first] = twoBusinessMember();
    $other = Organization::factory()->create();

    $this->actingAs($user)
        ->post(route('organizations.switch', $other))
        ->assertForbidden();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.id', $first->id));
});

test('superadmins switch only between their own memberships', function () {
    $admin = superadmin();
    $own = Organization::factory()->withMember($admin, MembershipRole::Owner)->create();
    $other = Organization::factory()->create();

    $this->actingAs($admin)
        ->post(route('organizations.switch', $other))
        ->assertForbidden()
        ->assertSessionMissing(CurrentOrganization::SESSION_KEY);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganization.id', $own->id)
            ->where('organizations', []));
});

test('guests cannot switch businesses', function () {
    $organization = Organization::factory()->create();

    $this->post(route('organizations.switch', $organization))
        ->assertRedirect(route('login'));
});

test('switcher is hidden with a single membership', function () {
    $user = User::factory()->create();
    Organization::factory()->withMember($user, MembershipRole::Owner)->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('organizations', []));

    $this->actingAs($user)
        ->get(route('scan'))
        ->assertInertia(fn (Assert $page) => $page->where('organizations', []));

    // The layouts render the switcher only for two or more businesses.
    $switcher = file_get_contents(resource_path('js/components/organization/organization-switcher.tsx'));
    expect($switcher)->toContain('organizations.length >= 2')
        ->and(substr_count($switcher, 'if (!visible)'))->toBe(2);

    expect(file_get_contents(resource_path('js/components/user-menu-content.tsx')))
        ->toContain('<UserMenuOrganizationSwitcher />');
    expect(file_get_contents(resource_path('js/layouts/reader-layout.tsx')))
        ->toContain('organizations.length >= 2 ? (')
        ->toContain('<ReaderOrganizationSwitcher />');
});

test('permissions follow the role in the switched business', function () {
    [$user, $managed, $staffed] = twoBusinessMember(MembershipRole::Manager, MembershipRole::Employee);
    Program::factory()->for($managed)->create();
    Program::factory()->for($staffed)->create();

    $settings = [
        'name' => 'Nuevo Nombre',
        'primary_color' => '#1E40AF',
        'currency' => 'MXN',
        'timezone' => 'America/Mexico_City',
    ];

    // Manager at the first business: can edit its settings.
    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.organization.update'), $settings)
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.edit'));

    expect($managed->refresh()->name)->toBe('Nuevo Nombre');

    // Employee at the second business: can't.
    $this->actingAs($user)->post(route('organizations.switch', $staffed))->assertRedirect(route('dashboard'));

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), [...$settings, 'name' => 'Otro Nombre'])
        ->assertForbidden();

    expect($staffed->refresh()->name)->toBe('Panadería Dos')
        ->and($managed->refresh()->name)->toBe('Nuevo Nombre');

    // Back to the first business: the manager role applies again.
    $this->actingAs($user)->post(route('organizations.switch', $managed));

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), [...$settings, 'name' => 'Tercer Nombre'])
        ->assertSessionHasNoErrors();

    expect($managed->refresh()->name)->toBe('Tercer Nombre');
});
