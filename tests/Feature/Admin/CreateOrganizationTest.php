<?php

use App\Enums\MembershipRole;
use App\Enums\ProgramType;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;

function organizationInput(array $overrides = []): array
{
    return [
        'name' => 'Acme Coffee',
        'slug' => 'acme-coffee',
        'owner_email' => 'New.Owner@Example.com',
        'owner_name' => '',
        'card_limit' => '50',
        'plan_notes' => 'Starter plan',
        ...$overrides,
    ];
}

test('creating an organization with a new owner email returns the password once', function () {
    Log::spy();
    $admin = superadmin();

    $response = $this->actingAs($admin)->post(route('admin.organizations.store'), organizationInput());

    $organization = Organization::query()->where('slug', 'acme-coffee')->sole();
    $response->assertRedirect(route('admin.organizations.show', $organization));

    // The password never sits in the session in plain text.
    $owner = User::query()->where('email', 'new.owner@example.com')->sole();
    $sessionAfterPost = serialize(session()->all());
    expect($sessionAfterPost)->not->toContain('new.owner@example.com');

    // The next page gets the password as one-time flash data.
    $password = null;
    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$password) {
            $page->component('admin/organizations/show')
                ->hasFlash('credentials.email', 'new.owner@example.com')
                ->hasFlash('credentials.password');

            $password = $page->toArray()['flash']['credentials']['password'];
        });

    expect($password)->toBeString()->toHaveLength(16)
        ->and(Hash::check($password, $owner->password))->toBeTrue()
        ->and($owner->password)->not->toBe($password)
        ->and($sessionAfterPost)->not->toContain($password)
        ->and(serialize(session()->all()))->not->toContain($password);

    // A second visit does not show it again.
    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('credentials'));

    // Owner, organization, membership, and default program were created.
    expect($owner->name)->toBe('new.owner')
        ->and($organization->name)->toBe('Acme Coffee')
        ->and($organization->card_limit)->toBe(50)
        ->and($organization->plan_notes)->toBe('Starter plan')
        ->and($owner->membershipFor($organization)?->role)->toBe(MembershipRole::Owner)
        ->and($organization->programs()->sole()->name)->toBe('Gift Card')
        ->and($organization->programs()->sole()->type)->toBe(ProgramType::Prepaid);

    // The owner can sign in with it.
    auth()->logout();
    $this->post(route('login.store'), ['email' => 'new.owner@example.com', 'password' => $password])
        ->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticatedAs($owner);

    // Nothing logged the password.
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('debug');
});

test('creating an organization with an existing email links that user', function () {
    $admin = superadmin();
    $existing = User::factory()->create(['email' => 'owner@example.com']);
    $hash = $existing->password;
    $other = Organization::factory()->withMember($existing)->create();

    $this->actingAs($admin)
        ->post(route('admin.organizations.store'), organizationInput(['owner_email' => ' OWNER@example.com ', 'card_limit' => '']))
        ->assertSessionHasNoErrors();

    $organization = Organization::query()->where('slug', 'acme-coffee')->sole();

    expect(User::query()->where('email', 'owner@example.com')->count())->toBe(1)
        ->and($existing->refresh()->password)->toBe($hash)
        ->and($existing->membershipFor($organization)?->role)->toBe(MembershipRole::Owner)
        ->and($existing->membershipFor($other))->not->toBeNull()
        ->and($organization->card_limit)->toBeNull();

    // No password to show for a linked user.
    $this->actingAs($admin)
        ->get(route('admin.organizations.show', $organization))
        ->assertInertia(fn (Assert $page) => $page->missingFlash('credentials'));
});

test('organization slug must be valid and unique', function (array $input, string $field) {
    Organization::factory()->create(['slug' => 'taken']);

    $this->actingAs(superadmin())
        ->post(route('admin.organizations.store'), organizationInput($input))
        ->assertSessionHasErrors($field);

    expect(Organization::query()->where('name', 'Acme Coffee')->exists())->toBeFalse()
        ->and(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse();
})->with([
    'taken slug' => [['slug' => 'taken'], 'slug'],
    'uppercase kept out' => [['slug' => 'bad slug!'], 'slug'],
    'double hyphen' => [['slug' => 'a--b'], 'slug'],
    'missing name' => [['name' => ''], 'name'],
    'bad email' => [['owner_email' => 'not-an-email'], 'owner_email'],
    'zero limit' => [['card_limit' => '0'], 'card_limit'],
]);

test('the new organization is usable by its owner right away', function () {
    $this->actingAs(superadmin())
        ->post(route('admin.organizations.store'), organizationInput(['owner_email' => 'fresh@example.com']));

    $owner = User::query()->where('email', 'fresh@example.com')->sole();

    $this->actingAs($owner)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors();

    expect(Organization::query()->where('slug', 'acme-coffee')->sole()->cards()->count())->toBe(1);
});

test('a failure while creating the organization rolls back the new owner', function () {
    Program::creating(fn () => throw new RuntimeException('program insert failed'));

    $this->withoutExceptionHandling();

    expect(fn () => $this->actingAs(superadmin())
        ->post(route('admin.organizations.store'), organizationInput()))
        ->toThrow(RuntimeException::class, 'program insert failed');

    expect(User::query()->where('email', 'new.owner@example.com')->exists())->toBeFalse()
        ->and(Organization::query()->where('slug', 'acme-coffee')->exists())->toBeFalse();
});
