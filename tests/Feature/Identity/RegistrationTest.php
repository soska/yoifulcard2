<?php

use App\Actions\Fortify\CreateNewUser;
use App\Enums\MembershipRole;
use App\Enums\ProgramType;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

test('registration creates user, organization, owner membership, and Gift Card program', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $user = User::where('email', 'ana@example.com')->sole();
    $organization = Organization::sole();

    expect($organization->name)->toBe("Ana Pérez's Business")
        ->and($organization->slug)->toBe('ana-perezs-business')
        ->and($organization->isWritable())->toBeTrue()
        ->and($organization->currency)->toBe('MXN')
        ->and($organization->primary_color)->toBe('#000000');

    $membership = Membership::sole();
    expect($membership->user_id)->toBe($user->id)
        ->and($membership->organization_id)->toBe($organization->id)
        ->and($membership->role)->toBe(MembershipRole::Owner);

    $program = Program::sole();
    expect($program->organization_id)->toBe($organization->id)
        ->and($program->name)->toBe('Gift Card')
        ->and($program->type)->toBe(ProgramType::Prepaid)
        ->and($program->is_active)->toBeTrue();

    $response->assertSessionHas(CurrentOrganization::SESSION_KEY, $organization->id);
});

test('registration rolls back everything when a step fails', function () {
    Program::creating(function (): void {
        throw new RuntimeException('Program insert failed');
    });

    expect(fn () => app(CreateNewUser::class)->create([
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]))->toThrow(RuntimeException::class, 'Program insert failed');

    expect(User::count())->toBe(0)
        ->and(Organization::count())->toBe(0)
        ->and(Membership::count())->toBe(0)
        ->and(Program::count())->toBe(0);
});

test('organization slugs are unique for duplicate names', function () {
    $action = app(CreateNewUser::class);

    foreach (['one', 'two', 'three'] as $n) {
        $action->create([
            'name' => 'Ana Pérez',
            'email' => "ana-{$n}@example.com",
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);
    }

    $slugs = Organization::pluck('slug');

    expect($slugs)->toHaveCount(3)
        ->and($slugs->unique())->toHaveCount(3)
        ->and($slugs->every(fn (string $slug) => str_starts_with($slug, 'ana-perezs-business')))->toBeTrue();
});

test('registration lands on the dashboard without email verification', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Ana Pérez',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'ana@example.com')->sole();

    expect($user->email_verified_at)->toBeNull();
    Notification::assertNothingSent();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard'));

    $this->get(route('scan'))->assertOk();
    $this->get(route('profile.edit'))->assertOk();
    $this->get('/email/verify')->assertNotFound();
});
