<?php

use App\Enums\EarnMode;
use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member with the given role in a new business with one program (rewards
 * off).
 *
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Organization, 2: Program}
 */
function rewardsSettingsMember(MembershipRole $role, array $organization = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create($organization);
    $program = Program::factory()->for($organization)->create();

    return [$user, $organization, $program];
}

test('an owner turns rewards on with a spend rule', function () {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Owner);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'spend', 'earn_rate' => '1.50'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.edit'))
        ->assertInertiaFlash('toast.code', 'settings.rewards_saved');

    expect($program->refresh()->rewards_enabled)->toBeTrue()
        ->and($program->earn_mode)->toBe(EarnMode::Spend)
        ->and($program->earn_rate)->toBe('1.50');

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('program.rewards', ['enabled' => true, 'mode' => 'spend', 'rate' => '1.50', 'visit_cap' => 3]));
});

test('a manager can switch to visits, which clears the rate', function () {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Manager);
    $program->update(['rewards_enabled' => true, 'earn_mode' => EarnMode::Spend, 'earn_rate' => '2.00']);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits', 'earn_rate' => '9'])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->earn_mode)->toBe(EarnMode::Visits)
        ->and($program->earn_rate)->toBeNull();
});

test('turning rewards off keeps the rule for later', function () {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Owner);
    $program->update(['rewards_enabled' => true, 'earn_mode' => EarnMode::Spend, 'earn_rate' => '2.00']);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '0'])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->rewards_enabled)->toBeFalse()
        ->and($program->earn_mode)->toBe(EarnMode::Spend)
        ->and($program->earn_rate)->toBe('2.00');
});

test('the rewards rule is validated', function (array $input, string|array $errors) {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Owner);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.rewards.update'), $input)
        ->assertSessionHasErrors($errors);

    expect($program->refresh()->rewards_enabled)->toBeFalse();
})->with([
    'a mode is needed' => [['rewards_enabled' => '1'], 'earn_mode'],
    'an unknown mode' => [['rewards_enabled' => '1', 'earn_mode' => 'stamps'], 'earn_mode'],
    'a rate is needed in spend mode' => [['rewards_enabled' => '1', 'earn_mode' => 'spend'], 'earn_rate'],
    'a rate above zero' => [['rewards_enabled' => '1', 'earn_mode' => 'spend', 'earn_rate' => '0'], 'earn_rate'],
    'a rate of up to two decimals' => [['rewards_enabled' => '1', 'earn_mode' => 'spend', 'earn_rate' => '1.005'], 'earn_rate'],
    'a rate that is not absurd' => [['rewards_enabled' => '1', 'earn_mode' => 'spend', 'earn_rate' => '1001'], 'earn_rate'],
    'the switch is needed' => [['earn_mode' => 'visits'], 'rewards_enabled'],
]);

test('an employee cannot change rewards settings', function () {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Employee);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits'])
        ->assertForbidden();

    expect($program->refresh()->rewards_enabled)->toBeFalse();
});

test('rewards settings are blocked for a suspended business', function () {
    [$user, , $program] = rewardsSettingsMember(MembershipRole::Owner, ['status' => 'suspended']);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits'])
        ->assertSessionHasErrors('organization');

    expect($program->refresh()->rewards_enabled)->toBeFalse();
});
