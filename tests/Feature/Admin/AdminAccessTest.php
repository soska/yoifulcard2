<?php

use App\Enums\CardStatus;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Models\Program;
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
        'batch store' => ['post', fn (Organization $organization) => route('admin.organizations.batches.store', $organization), ['count' => 5]],
        'batch show' => ['get', fn (Organization $organization) => route('admin.batches.show', adminAccessBatch($organization)), []],
        'batch void' => ['post', fn (Organization $organization) => route('admin.batches.void', adminAccessBatch($organization)), []],
        'users' => ['get', fn () => route('admin.users.index'), []],
        'password' => ['post', fn (Organization $organization, User $target) => route('admin.users.password', $target), []],
        'grant' => ['post', fn (Organization $organization, User $target) => route('admin.users.superadmin.store', $target), []],
        'revoke' => ['delete', fn (Organization $organization, User $target) => route('admin.users.superadmin.destroy', $target), []],
    ];
}

/**
 * A batch of the organization with one inactive card, for the batch routes.
 */
function adminAccessBatch(Organization $organization): CardBatch
{
    $program = $organization->programs()->first() ?? Program::factory()->for($organization)->create();
    $batch = CardBatch::factory()->for($program)->create(['count' => 1]);
    Card::factory()->for($program)->inactive()->create(['batch_id' => $batch->id]);

    return $batch;
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
        ->and(Card::query()->where('status', '<>', CardStatus::Inactive)->exists())->toBeFalse()
        ->and(CardBatch::query()->whereNotNull('voided_at')->exists())->toBeFalse()
        ->and(Card::count())->toBe(CardBatch::count())
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
