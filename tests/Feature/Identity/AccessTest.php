<?php

use App\Models\Superadmin;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected from dashboard, scan, and admin', function (string $uri) {
    $this->get($uri)->assertRedirect(route('login'));
})->with(['/dashboard', '/scan', '/admin']);

test('guests cannot claim superadmin', function () {
    $this->post(route('admin.claim'))->assertRedirect(route('login'));

    expect(Superadmin::count())->toBe(0);
});

test('first user can claim superadmin while the table is empty', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.claim'))
        ->assertRedirect(route('admin.index'));

    $superadmin = Superadmin::sole();
    expect($superadmin->user_id)->toBe($user->id)
        ->and($superadmin->granted_by)->toBe($user->id)
        ->and($superadmin->granted_at)->not->toBeNull()
        ->and($user->isSuperadmin())->toBeTrue();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('admin/index'));
});

test('claim is forbidden once a superadmin exists', function () {
    Superadmin::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('admin.claim'))
        ->assertForbidden();

    expect(Superadmin::count())->toBe(1)
        ->and($user->isSuperadmin())->toBeFalse();
});

test('non-superadmin gets 403 on admin', function () {
    Superadmin::factory()->create();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/forbidden')
            ->where('canClaimSuperadmin', false));
});

test('forbidden admin page offers the claim while no superadmin exists', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.index'))
        ->assertForbidden()
        ->assertInertia(fn (Assert $page) => $page
            ->component('errors/forbidden')
            ->where('canClaimSuperadmin', true));
});
