<?php

use App\Models\Organization;
use App\Models\Superadmin;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('superadmin can list and search users', function () {
    $admin = superadmin();
    $alice = User::factory()->create(['name' => 'Alice Owner', 'email' => 'alice@example.com']);
    User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);
    $organization = Organization::factory()->withMember($alice)->create(['name' => 'Alice Cafe']);

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/users/index')
            ->has('users.data', 3));

    $this->actingAs($admin)
        ->get(route('admin.users.index', ['q' => 'ALICE@']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.id', $alice->id)
            ->where('users.data.0.is_superadmin', false)
            ->where('users.data.0.memberships.0.organization_id', $organization->id)
            ->where('users.data.0.memberships.0.organization', 'Alice Cafe')
            ->where('users.data.0.memberships.0.role', 'owner')
            ->missing('users.data.0.password'));
});

test('superadmin can set a temporary password that is shown once and works for login', function () {
    $admin = superadmin();
    $user = User::factory()->create(['email' => 'locked.out@example.com']);
    $oldHash = $user->password;
    $oldRemember = $user->remember_token;

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.password', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $sessionAfterPost = serialize(session()->all());

    $password = null;
    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$password) {
            $page->hasFlash('credentials.email', 'locked.out@example.com')
                ->hasFlash('credentials.password');

            $password = $page->toArray()['flash']['credentials']['password'];
        });

    $user->refresh();
    expect($password)->toBeString()->toHaveLength(16)
        ->and($user->password)->not->toBe($oldHash)
        ->and($user->password)->not->toBe($password)
        ->and(Hash::check($password, $user->password))->toBeTrue()
        ->and(Hash::check('password', $user->password))->toBeFalse()
        ->and($user->remember_token)->not->toBe($oldRemember)
        ->and($sessionAfterPost)->not->toContain($password)
        ->and(serialize(session()->all()))->not->toContain($password);

    // Shown once: the next visit has no credentials.
    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('credentials'));

    // The old password fails and the temporary one signs in.
    auth()->logout();

    $this->post(route('login.store'), ['email' => 'locked.out@example.com', 'password' => 'password']);
    $this->assertGuest();

    $this->post(route('login.store'), ['email' => 'locked.out@example.com', 'password' => $password])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($user);
});

test('each temporary password is different', function () {
    $admin = superadmin();
    $user = User::factory()->create();
    $passwords = [];

    foreach (range(1, 2) as $attempt) {
        $this->actingAs($admin)->post(route('admin.users.password', $user));
        $this->actingAs($admin)
            ->get(route('admin.users.index'))
            ->assertInertia(function (Assert $page) use (&$passwords) {
                $passwords[] = $page->toArray()['flash']['credentials']['password'];
            });
    }

    expect($passwords[0])->not->toBe($passwords[1])
        ->and(Hash::check($passwords[1], $user->refresh()->password))->toBeTrue();
});

test('superadmin can grant and revoke superadmin', function () {
    $admin = superadmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->post(route('admin.users.superadmin.store', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    $row = Superadmin::query()->where('user_id', $user->id)->sole();
    expect($row->granted_by)->toBe($admin->id)
        ->and($row->granted_at)->not->toBeNull()
        ->and($user->isSuperadmin())->toBeTrue();

    // Granting again changes nothing.
    $this->actingAs($admin)->post(route('admin.users.superadmin.store', $user));
    expect(Superadmin::query()->where('user_id', $user->id)->count())->toBe(1);

    // The new superadmin can open admin.
    $this->actingAs($user)->get(route('admin.index'))->assertRedirect(route('admin.organizations.index'));

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.superadmin.destroy', $user))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasNoErrors();

    expect($user->isSuperadmin())->toBeFalse();
    $this->actingAs($user)->get(route('admin.index'))->assertForbidden();
});

test('last superadmin cannot be revoked', function () {
    $admin = superadmin();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.superadmin.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHasErrors(['superadmin' => 'The last superadmin cannot be revoked. Grant the role to someone else first.']);

    expect($admin->isSuperadmin())->toBeTrue()
        ->and(Superadmin::count())->toBe(1);

    $this->actingAs($admin)->get(route('admin.index'))->assertRedirect(route('admin.organizations.index'));
});

test('superadmin can revoke themselves when another remains', function () {
    $admin = superadmin();
    $other = superadmin();

    $this->actingAs($admin)
        ->from(route('admin.users.index'))
        ->delete(route('admin.users.superadmin.destroy', $admin))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHasNoErrors();

    expect($admin->isSuperadmin())->toBeFalse()
        ->and($other->isSuperadmin())->toBeTrue();

    $this->actingAs($admin)->get(route('admin.index'))->assertForbidden();

    // Now the other one is the last, and cannot revoke themselves.
    $this->actingAs($other)
        ->delete(route('admin.users.superadmin.destroy', $other))
        ->assertSessionHasErrors('superadmin');

    expect($other->isSuperadmin())->toBeTrue();
});

test('revoking a user who is not a superadmin changes nothing', function () {
    $admin = superadmin();
    $user = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.superadmin.destroy', $user))
        ->assertSessionHasNoErrors();

    expect(Superadmin::count())->toBe(1);
});
