<?php

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Models\Organization;
use App\Models\Superadmin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Every admin route except the one-time claim, with a request to send.
 *
 * @return array<string, array{0: string, 1: Closure(Organization, User): string, 2: array<string, mixed>}>
 */
function adminRoutes(): array
{
    return [
        'overview' => ['get', fn () => route('admin.index'), []],
        'organizations' => ['get', fn () => route('admin.organizations.index'), []],
        'create form' => ['get', fn () => route('admin.organizations.create'), []],
        'store' => ['post', fn () => route('admin.organizations.store'), ['name' => 'X', 'slug' => 'x-org', 'owner_email' => 'x@example.com']],
        'show' => ['get', fn (Organization $organization) => route('admin.organizations.show', $organization), []],
        'update' => ['patch', fn (Organization $organization) => route('admin.organizations.update', $organization), ['card_limit' => 1, 'plan_notes' => 'x']],
        'suspend' => ['post', fn (Organization $organization) => route('admin.organizations.suspend', $organization), []],
        'reactivate' => ['post', fn (Organization $organization) => route('admin.organizations.reactivate', $organization), []],
        'users' => ['get', fn () => route('admin.users.index'), []],
        'password' => ['post', fn (Organization $organization, User $target) => route('admin.users.password', $target), []],
        'grant' => ['post', fn (Organization $organization, User $target) => route('admin.users.superadmin.store', $target), []],
        'revoke' => ['delete', fn (Organization $organization, User $target) => route('admin.users.superadmin.destroy', $target), []],
    ];
}

test('non-superadmin gets 403 on every admin route', function (MembershipRole $role) {
    $admin = superadmin();
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create(['card_limit' => 5]);
    $target = User::factory()->create();
    $targetHash = $target->password;

    foreach (adminRoutes() as $name => [$method, $url, $data]) {
        $this->actingAs($user)
            ->{$method}($url($organization, $target), $data)
            ->assertForbidden();
    }

    // Nothing changed.
    $organization->refresh();
    expect($organization->status)->toBe(OrganizationStatus::Active)
        ->and($organization->card_limit)->toBe(5)
        ->and($organization->plan_notes)->toBeNull()
        ->and(Organization::query()->where('slug', 'x-org')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'x@example.com')->exists())->toBeFalse()
        ->and($target->refresh()->password)->toBe($targetHash)
        ->and(Superadmin::query()->pluck('user_id')->all())->toBe([$admin->id]);
})->with([MembershipRole::Owner, MembershipRole::Manager, MembershipRole::Employee]);

test('guests are sent to login from every admin route', function () {
    $organization = Organization::factory()->create();
    $target = User::factory()->create();

    foreach (adminRoutes() as [$method, $url, $data]) {
        $this->{$method}($url($organization, $target), $data)->assertRedirect(route('login'));
    }
});

test('superadmin sees admin links in the shared props', function () {
    $this->actingAs(superadmin())
        ->get(route('admin.index'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', true));

    [$owner] = cardOwner();

    $this->actingAs($owner)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('auth.isSuperadmin', false));
});
