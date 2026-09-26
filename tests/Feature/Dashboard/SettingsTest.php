<?php

use App\Enums\MembershipRole;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member with the given role in a new organization with one program.
 *
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Organization, 2: Program}
 */
function settingsMember(MembershipRole $role, array $organization = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create([
        'name' => 'Old Name',
        'primary_color' => '#000000',
        'currency' => 'MXN',
        ...$organization,
    ]);
    $program = Program::factory()->for($organization)->create(['name' => 'Gift Card', 'terms_url' => null]);

    return [$user, $organization, $program];
}

/**
 * @return array<string, string>
 */
function organizationSettings(array $overrides = []): array
{
    return [
        'name' => 'Café Luna',
        'primary_color' => '#1E40AF',
        'currency' => 'USD',
        'timezone' => 'America/Tijuana',
        ...$overrides,
    ];
}

test('owner and manager can update organization and program settings', function (MembershipRole $role) {
    [$user, $organization, $program] = settingsMember($role);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.organization.update'), organizationSettings())
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.edit'));

    $organization->refresh();

    expect($organization->name)->toBe('Café Luna')
        ->and($organization->primary_color)->toBe('#1e40af')
        ->and($organization->currency)->toBe('USD')
        ->and($organization->timezone)->toBe('America/Tijuana');

    $this->actingAs($user)
        ->patch(route('settings.program.update'), ['name' => 'Tarjeta de regalo', 'terms_url' => 'https://luna.example/terms'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('settings.edit'));

    expect($program->refresh()->name)->toBe('Tarjeta de regalo')
        ->and($program->terms_url)->toBe('https://luna.example/terms');

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/business')
            ->where('canEdit', true)
            ->where('organization.name', 'Café Luna')
            ->where('organization.timezone', 'America/Tijuana')
            ->where('program.name', 'Tarjeta de regalo')
            ->where('currentOrganization.timezone', 'America/Tijuana'));
})->with([
    'owner' => MembershipRole::Owner,
    'manager' => MembershipRole::Manager,
]);

test('employee cannot update settings', function () {
    [$user, $organization, $program] = settingsMember(MembershipRole::Employee);

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/business')
            ->where('canEdit', false)
            ->where('organization.name', 'Old Name'));

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), organizationSettings())
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('settings.program.update'), ['name' => 'Hacked', 'terms_url' => null])
        ->assertForbidden();

    expect($organization->refresh()->name)->toBe('Old Name')
        ->and($program->refresh()->name)->toBe('Gift Card');
});

test('settings updates are blocked for a suspended organization', function (string $status) {
    [$user, $organization, $program] = settingsMember(MembershipRole::Owner, ['status' => $status]);

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('canEdit', false)
            ->where('organization.name', 'Old Name'));

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.organization.update'), organizationSettings())
        ->assertRedirect(route('settings.edit'))
        ->assertSessionHasErrors(['organization' => 'This business is suspended. Contact support.']);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.program.update'), ['name' => 'New', 'terms_url' => null])
        ->assertSessionHasErrors('organization');

    expect($organization->refresh()->name)->toBe('Old Name')
        ->and($organization->currency)->toBe('MXN')
        ->and($program->refresh()->name)->toBe('Gift Card');
})->with(['suspended', 'cancelled']);

test('settings change only the current organization', function () {
    [, $organization] = settingsMember(MembershipRole::Owner);
    [$stranger, $theirs] = settingsMember(MembershipRole::Owner, ['name' => 'Theirs']);

    $this->actingAs($stranger)
        ->patch(route('settings.organization.update'), organizationSettings(['name' => 'Changed']))
        ->assertSessionHasNoErrors();

    // Only the stranger's own current organization changed.
    expect($organization->refresh()->name)->toBe('Old Name')
        ->and($theirs->refresh()->name)->toBe('Changed');
});

test('organization settings are validated', function (array $input, string $field) {
    [$user, $organization] = settingsMember(MembershipRole::Owner);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.organization.update'), organizationSettings($input))
        ->assertSessionHasErrors($field);

    expect($organization->refresh()->name)->toBe('Old Name');
})->with([
    'missing name' => [['name' => '  '], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'bad color' => [['primary_color' => 'blue'], 'primary_color'],
    'short color' => [['primary_color' => '#fff'], 'primary_color'],
    'unknown currency' => [['currency' => 'XYZ'], 'currency'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus_Mons'], 'timezone'],
    'svg logo' => [['logo' => UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml')], 'logo'],
    'pdf logo' => [['logo' => UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf')], 'logo'],
    'huge logo' => [['logo' => UploadedFile::fake()->image('logo.png')->size(4096)], 'logo'],
]);

test('program settings are validated', function (array $input, string $field) {
    [$user, , $program] = settingsMember(MembershipRole::Owner);

    $this->actingAs($user)
        ->from(route('settings.edit'))
        ->patch(route('settings.program.update'), ['name' => 'Gift Card', 'terms_url' => null, ...$input])
        ->assertSessionHasErrors($field);

    expect($program->refresh()->terms_url)->toBeNull();
})->with([
    'missing name' => [['name' => ''], 'name'],
    'not a url' => [['terms_url' => 'terms page'], 'terms_url'],
    'javascript url' => [['terms_url' => 'javascript:alert(1)'], 'terms_url'],
]);

test('an empty terms url clears it', function () {
    [$user, , $program] = settingsMember(MembershipRole::Owner);
    $program->update(['terms_url' => 'https://luna.example/terms']);

    $this->actingAs($user)
        ->patch(route('settings.program.update'), ['name' => 'Gift Card', 'terms_url' => ''])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->terms_url)->toBeNull();
});

test('logo upload stores on the public disk and shows on the public card', function () {
    Storage::fake('public');

    [$user, $organization] = settingsMember(MembershipRole::Owner);
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '42.00']);

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), organizationSettings([
            'primary_color' => '#AA3300',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
        ]))
        ->assertSessionHasNoErrors();

    $organization->refresh();
    $files = Storage::disk('public')->allFiles('logos/'.$organization->id);

    expect($files)->toHaveCount(1)
        ->and($organization->logo_url)->toBe(Storage::disk('public')->url($files[0]));

    Storage::disk('public')->assertExists($files[0]);

    auth()->logout();

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->where('organization.name', 'Café Luna')
            ->where('organization.logo_url', $organization->logo_url)
            ->where('organization.primary_color', '#aa3300')
            ->where('organization.currency', 'USD')
            ->where('card.balance', '42.00'));

    // A new logo replaces the old file; removing it deletes the file.
    $this->actingAs($user)
        ->patch(route('settings.organization.update'), organizationSettings([
            'logo' => UploadedFile::fake()->image('new.jpg', 100, 100),
        ]))
        ->assertSessionHasNoErrors();

    $replaced = Storage::disk('public')->allFiles('logos/'.$organization->id);

    expect($replaced)->toHaveCount(1)
        ->and($replaced[0])->not->toBe($files[0])
        ->and($organization->refresh()->logo_url)->toBe(Storage::disk('public')->url($replaced[0]));

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), organizationSettings(['remove_logo' => '1']))
        ->assertSessionHasNoErrors();

    expect($organization->refresh()->logo_url)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos/'.$organization->id))->toBe([]);
});

test('saving without a new logo keeps the current one', function () {
    Storage::fake('public');

    [$user, $organization] = settingsMember(MembershipRole::Owner, ['logo_url' => 'https://cdn.example/logo.png']);

    $this->actingAs($user)
        ->patch(route('settings.organization.update'), organizationSettings())
        ->assertSessionHasNoErrors();

    expect($organization->refresh()->logo_url)->toBe('https://cdn.example/logo.png');
});

test('usage warning at 80 percent and block at 100 percent', function () {
    [$user, $organization] = settingsMember(MembershipRole::Owner, ['card_limit' => 10]);

    Card::factory()->count(7)->forOrganization($organization)->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.used', 7)
            ->where('usage.limit', 10)
            ->where('usage.percent', 70)
            ->where('usage.nearLimit', false)
            ->where('usage.atLimit', false));

    Card::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.used', 8)
            ->where('usage.percent', 80)
            ->where('usage.nearLimit', true)
            ->where('usage.atLimit', false));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.nearLimit', true)
            ->where('canCreateCards', true));

    Card::factory()->count(2)->forOrganization($organization)->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.used', 10)
            ->where('usage.percent', 100)
            ->where('usage.nearLimit', false)
            ->where('usage.atLimit', true));

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('usage.atLimit', true));

    $this->actingAs($user)
        ->from(route('cards.create'))
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasErrors('card_limit');

    expect(Card::query()->forOrganization($organization)->count())->toBe(10);
});

test('unlimited plan shows usage without a limit', function () {
    [$user, $organization] = settingsMember(MembershipRole::Owner);
    Card::factory()->count(3)->forOrganization($organization)->create();

    $this->actingAs($user)
        ->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.used', 3)
            ->where('usage.limit', null)
            ->where('usage.nearLimit', false)
            ->where('usage.atLimit', false));
});

test('settings page requires login', function () {
    $this->get(route('settings.edit'))->assertRedirect(route('login'));
    $this->patch(route('settings.organization.update'), organizationSettings())->assertRedirect(route('login'));
});
