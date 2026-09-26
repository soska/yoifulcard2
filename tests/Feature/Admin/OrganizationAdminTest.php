<?php

use App\Enums\OrganizationStatus;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin can list and search organizations', function () {
    $admin = superadmin();
    $coffee = Organization::factory()->create(['name' => 'Acme Coffee', 'slug' => 'acme-coffee']);
    $bakery = Organization::factory()->suspended()->create(['name' => 'Blue Bakery', 'slug' => 'blue-bakery']);
    Organization::factory()->create(['name' => 'Corner Shop', 'slug' => 'corner-shop']);

    $owner = User::factory()->create();
    $coffee->memberships()->create(['user_id' => $owner->id, 'role' => 'owner']);
    $program = Program::factory()->for($coffee)->create();
    Card::factory()->count(2)->for($program)->create();

    $this->actingAs($admin)
        ->get(route('admin.organizations.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/index')
            ->has('organizations.data', 3)
            ->where('organizations.total', 3));

    // Search matches the name or the slug, case-insensitively.
    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['q' => 'COFFEE']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $coffee->id)
            ->where('organizations.data.0.members_count', 1)
            ->where('organizations.data.0.cards_count', 2)
            ->where('filters.q', 'COFFEE'));

    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['q' => 'blue-bak']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $bakery->id));

    // LIKE wildcards in the search are literal.
    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['q' => '%']))
        ->assertInertia(fn (Assert $page) => $page->has('organizations.data', 0));

    // Status filter; unknown statuses are ignored.
    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['status' => 'suspended']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('organizations.data', 1)
            ->where('organizations.data.0.id', $bakery->id)
            ->where('filters.status', 'suspended'));

    $this->actingAs($admin)
        ->get(route('admin.organizations.index', ['status' => 'bogus']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('organizations.data', 3)
            ->where('filters.status', null));
});

test('superadmin can open an organization and see its usage and plan', function () {
    $admin = superadmin();
    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $organization = Organization::factory()->withMember($owner)->create([
        'card_limit' => 10,
        'plan_notes' => 'Starter plan',
    ]);
    $program = Program::factory()->for($organization)->create();
    Card::factory()->count(8)->for($program)->create(['balance' => '12.50']);

    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/organizations/show')
            ->where('organization.id', $organization->id)
            ->where('organization.status', 'active')
            ->where('organization.card_limit', 10)
            ->where('organization.plan_notes', 'Starter plan')
            ->where('usage.used', 8)
            ->where('usage.limit', 10)
            ->where('usage.percent', 80)
            ->where('usage.nearLimit', true)
            ->where('stats.active', 8)
            ->where('stats.outstandingBalance', '100.00')
            ->has('members', 1)
            ->where('members.0.email', 'owner@example.com')
            ->where('members.0.role', 'owner')
            ->has('programs', 1));
});

test('unknown organization ids return 404', function () {
    $this->actingAs(superadmin())
        ->get('/admin/organizations/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();

    $this->actingAs(superadmin())
        ->get('/admin/organizations/not-a-uuid')
        ->assertNotFound();
});

test('superadmin can set card limit and plan notes', function () {
    $admin = superadmin();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)
        ->patch(route('admin.organizations.update', $organization), [
            'card_limit' => '25',
            'plan_notes' => 'Growth plan, billed yearly',
        ])
        ->assertRedirect(route('admin.organizations.show', $organization))
        ->assertSessionHasNoErrors();

    $organization->refresh();
    expect($organization->card_limit)->toBe(25)
        ->and($organization->plan_notes)->toBe('Growth plan, billed yearly');

    // Empty values clear the limit (unlimited) and the notes.
    $this->actingAs($admin)
        ->patch(route('admin.organizations.update', $organization), [
            'card_limit' => '',
            'plan_notes' => '',
        ])
        ->assertSessionHasNoErrors();

    $organization->refresh();
    expect($organization->card_limit)->toBeNull()
        ->and($organization->plan_notes)->toBeNull()
        ->and($organization->cardUsage()['limit'])->toBeNull();
});

test('card limit must be a positive whole number', function (mixed $limit) {
    $organization = Organization::factory()->create(['card_limit' => 5]);

    $this->actingAs(superadmin())
        ->patch(route('admin.organizations.update', $organization), [
            'card_limit' => $limit,
            'plan_notes' => null,
        ])
        ->assertSessionHasErrors('card_limit');

    expect($organization->refresh()->card_limit)->toBe(5);
})->with([0, -3, '2.5', 'ten', 1000001]);

test('a lowered card limit blocks card creation for the business', function () {
    [$owner, $organization, $program] = cardOwner();
    Card::factory()->count(2)->for($program)->create();

    $this->actingAs(superadmin())
        ->patch(route('admin.organizations.update', $organization), ['card_limit' => 2, 'plan_notes' => null])
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasErrors('card_limit');

    expect($organization->cards()->count())->toBe(2);
});

test('superadmin can suspend and reactivate', function () {
    $admin = superadmin();
    $organization = Organization::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.organizations.suspend', $organization))
        ->assertRedirect(route('admin.organizations.show', $organization))
        ->assertSessionHasNoErrors();

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Suspended)
        ->and($organization->isWritable())->toBeFalse();

    // Suspending twice is refused, and nothing changes.
    $this->actingAs($admin)
        ->post(route('admin.organizations.suspend', $organization))
        ->assertSessionHasErrors('status');

    $this->actingAs($admin)
        ->post(route('admin.organizations.reactivate', $organization))
        ->assertRedirect(route('admin.organizations.show', $organization))
        ->assertSessionHasNoErrors();

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active)
        ->and($organization->isWritable())->toBeTrue();

    $this->actingAs($admin)
        ->post(route('admin.organizations.reactivate', $organization))
        ->assertSessionHasErrors('status');

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Active);
});

test('cancelled organizations are not changed from admin', function () {
    $admin = superadmin();
    $organization = Organization::factory()->cancelled()->create();

    $this->actingAs($admin)->post(route('admin.organizations.suspend', $organization))->assertSessionHasErrors('status');
    $this->actingAs($admin)->post(route('admin.organizations.reactivate', $organization))->assertSessionHasErrors('status');

    expect($organization->refresh()->status)->toBe(OrganizationStatus::Cancelled);
});

test('admin overview shows platform totals', function () {
    $admin = superadmin();
    Organization::factory()->count(2)->create();
    Organization::factory()->suspended()->create();

    $this->actingAs($admin)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/index')
            ->where('stats.organizations', 3)
            ->where('stats.activeOrganizations', 2)
            ->where('stats.suspendedOrganizations', 1)
            ->where('stats.users', 1)
            ->where('stats.superadmins', 1)
            ->has('recentOrganizations', 3));
});
