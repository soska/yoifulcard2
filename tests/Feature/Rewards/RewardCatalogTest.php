<?php

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Reward;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member with the given role in a new business with one program.
 *
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Organization, 2: Program}
 */
function catalogMember(MembershipRole $role = MembershipRole::Owner, array $organization = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create($organization);
    $program = Program::factory()->for($organization)->create();

    return [$user, $organization, $program];
}

test('an owner sees the catalog, cheapest first, with inactive rewards too', function () {
    [$user, , $program] = catalogMember();
    Reward::factory()->for($program)->create(['name' => 'Free coffee', 'points_cost' => 10]);
    Reward::factory()->for($program)->inactive()->create(['name' => 'Old promo', 'points_cost' => 3]);
    Reward::factory()->create(['name' => 'Someone else\'s']);

    $this->actingAs($user)->get(route('rewards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('rewards/index')
            ->has('rewards', 2)
            ->where('rewards.0.name', 'Old promo')
            ->where('rewards.0.is_active', false)
            ->where('rewards.1.name', 'Free coffee')
            ->where('rewardsEnabled', false)
            ->where('canEdit', true));
});

test('employees cannot open the catalog', function () {
    [$user] = catalogMember(MembershipRole::Employee);

    $this->actingAs($user)->get(route('rewards.index'))->assertForbidden();
});

test('a suspended business can look at its catalog but not change it', function () {
    [$user, , $program] = catalogMember(organization: ['status' => 'suspended']);
    $reward = Reward::factory()->for($program)->create(['name' => 'Free coffee']);

    $this->actingAs($user)->get(route('rewards.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canEdit', false));

    $this->actingAs($user)->from(route('rewards.index'))
        ->post(route('rewards.store'), ['name' => 'New', 'points_cost' => '5'])
        ->assertSessionHasErrors('organization');
    $this->actingAs($user)->from(route('rewards.index'))
        ->patch(route('rewards.update', $reward), ['name' => 'Changed', 'points_cost' => '5'])
        ->assertSessionHasErrors('organization');

    expect(Reward::count())->toBe(1)
        ->and($reward->fresh()->name)->toBe('Free coffee');
});

test('an owner adds a reward and a manager edits and switches it off', function () {
    [$owner, $organization, $program] = catalogMember();

    $this->actingAs($owner)
        ->post(route('rewards.store'), ['name' => '  Free coffee  ', 'points_cost' => '10'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('rewards.index'))
        ->assertInertiaFlash('toast.code', 'rewards.saved');

    $reward = Reward::sole();
    expect($reward->program_id)->toBe($program->id)
        ->and($reward->name)->toBe('Free coffee')
        ->and($reward->points_cost)->toBe(10)
        ->and($reward->is_active)->toBeTrue();

    $manager = User::factory()->create();
    $organization->memberships()->create(['user_id' => $manager->id, 'role' => MembershipRole::Manager]);

    $this->actingAs($manager)
        ->patch(route('rewards.update', $reward), ['name' => 'Free large coffee', 'points_cost' => '12', 'is_active' => '0'])
        ->assertSessionHasNoErrors();

    expect($reward->refresh()->name)->toBe('Free large coffee')
        ->and($reward->points_cost)->toBe(12)
        ->and($reward->is_active)->toBeFalse();

    // An edit without the switch leaves it as it was.
    $this->actingAs($manager)
        ->patch(route('rewards.update', $reward), ['name' => 'Free large coffee', 'points_cost' => '15'])
        ->assertSessionHasNoErrors();

    expect($reward->refresh()->points_cost)->toBe(15)
        ->and($reward->is_active)->toBeFalse();
});

test('a reward needs a name and a cost of at least one point', function (array $input, string $field) {
    [$user] = catalogMember();

    $this->actingAs($user)->from(route('rewards.index'))
        ->post(route('rewards.store'), $input)
        ->assertSessionHasErrors($field);

    expect(Reward::count())->toBe(0);
})->with([
    'no name' => [['name' => '', 'points_cost' => '5'], 'name'],
    'a name that is too long' => [['name' => str_repeat('a', 121), 'points_cost' => '5'], 'name'],
    'no cost' => [['name' => 'Coffee'], 'points_cost'],
    'a zero cost' => [['name' => 'Coffee', 'points_cost' => '0'], 'points_cost'],
    'a negative cost' => [['name' => 'Coffee', 'points_cost' => '-4'], 'points_cost'],
    'a fractional cost' => [['name' => 'Coffee', 'points_cost' => '2.5'], 'points_cost'],
    'a cost that is absurd' => [['name' => 'Coffee', 'points_cost' => '1000001'], 'points_cost'],
]);

test('employees cannot add or change rewards', function () {
    [$user, , $program] = catalogMember(MembershipRole::Employee);
    $reward = Reward::factory()->for($program)->create(['name' => 'Free coffee']);

    $this->actingAs($user)->post(route('rewards.store'), ['name' => 'New', 'points_cost' => '5'])->assertForbidden();
    $this->actingAs($user)->patch(route('rewards.update', $reward), ['name' => 'Hacked', 'points_cost' => '1'])->assertForbidden();

    expect(Reward::count())->toBe(1)
        ->and($reward->fresh()->name)->toBe('Free coffee');
});

test('another business\'s reward is a 404', function () {
    [$user] = catalogMember();
    $theirs = Reward::factory()->create(['name' => 'Theirs']);

    $this->actingAs($user)
        ->patch(route('rewards.update', $theirs), ['name' => 'Mine now', 'points_cost' => '1'])
        ->assertNotFound();

    expect($theirs->fresh()->name)->toBe('Theirs');
});
