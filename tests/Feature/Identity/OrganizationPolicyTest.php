<?php

use App\Enums\MembershipRole;
use App\Http\Middleware\EnsureOrganizationWritable;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

test('employee cannot update organization settings', function () {
    $employee = User::factory()->create();
    $organization = Organization::factory()->withMember($employee, MembershipRole::Employee)->create();

    expect(Gate::forUser($employee)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($employee)->denies('update', $organization))->toBeTrue();
});

test('owners and managers can update an active organization', function (MembershipRole $role) {
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create();

    expect(Gate::forUser($user)->allows('update', $organization))->toBeTrue();
})->with([MembershipRole::Owner, MembershipRole::Manager]);

test('nobody can update a suspended or cancelled organization', function (string $state) {
    $owner = User::factory()->create();
    $organization = Organization::factory()->{$state}()->withMember($owner)->create();

    expect($organization->isWritable())->toBeFalse()
        ->and(Gate::forUser($owner)->allows('view', $organization))->toBeTrue()
        ->and(Gate::forUser($owner)->denies('update', $organization))->toBeTrue();
})->with(['suspended', 'cancelled']);

test('non-members cannot view or update an organization', function () {
    $outsider = User::factory()->create();
    $organization = Organization::factory()->create();

    expect(Gate::forUser($outsider)->denies('view', $organization))->toBeTrue()
        ->and(Gate::forUser($outsider)->denies('update', $organization))->toBeTrue();
});

test('writable middleware blocks writes for a suspended organization', function () {
    Route::middleware(['web', 'auth', 'organization', EnsureOrganizationWritable::class])
        ->post('/_test/write', fn () => response('written'));

    $owner = User::factory()->create();
    $organization = Organization::factory()->withMember($owner)->create();

    $this->actingAs($owner)->post('/_test/write')->assertOk()->assertSee('written');

    $organization->update(['status' => 'suspended']);

    $this->actingAs($owner)
        ->from('/dashboard')
        ->post('/_test/write')
        ->assertRedirect('/dashboard')
        ->assertSessionHasErrors('organization');
});
