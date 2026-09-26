<?php

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Inertia\Testing\AssertableInertia as Assert;

test('login restores the current organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, MembershipRole::Owner)->create();

    // A later membership must not replace the first one.
    $this->travel(1)->minute();
    Membership::factory()->for($user)->manager()->create();

    $login = fn () => $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $login()->assertSessionHas(CurrentOrganization::SESSION_KEY, $organization->id);

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();

    $login()->assertSessionHas(CurrentOrganization::SESSION_KEY, $organization->id);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('currentOrganization', [
                'id' => $organization->id,
                'name' => $organization->name,
                'status' => 'active',
                'role' => 'owner',
            ]));
});

test('user without membership sees the empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('organizations/empty')
            ->where('currentOrganization', null));

    $this->actingAs($user)
        ->get(route('scan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('organizations/empty'));
});

test('a stale organization in the session falls back to the first membership', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create();
    $other = Organization::factory()->create();

    $this->actingAs($user)
        ->withSession([CurrentOrganization::SESSION_KEY => $other->id])
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.id', $organization->id))
        ->assertSessionHas(CurrentOrganization::SESSION_KEY, $organization->id);
});
