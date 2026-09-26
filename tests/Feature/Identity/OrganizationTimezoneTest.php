<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('new organizations default to America/Mexico_City', function () {
    // Registration creates the organization without naming a timezone.
    $user = app(CreateNewUser::class)->create([
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
    $organization = $user->organizations()->sole();

    expect($organization->timezone)->toBe('America/Mexico_City')
        ->and($organization->fresh()->timezone)->toBe('America/Mexico_City');

    // The column default covers rows written without the model.
    $id = (string) str()->uuid();
    DB::table('organizations')->insert(['id' => $id, 'name' => 'Raw', 'slug' => 'raw-'.$id]);
    expect(DB::table('organizations')->where('id', $id)->value('timezone'))->toBe('America/Mexico_City');

    // And the timezone reaches the shared prop.
    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('currentOrganization.timezone', 'America/Mexico_City'));
});

test('organization timezone must be in PHP\'s timezone list', function () {
    $organization = Organization::factory()->create(['timezone' => 'Europe/Madrid']);
    expect($organization->fresh()->timezone)->toBe('Europe/Madrid');

    expect(fn () => $organization->update(['timezone' => 'Mars/Olympus_Mons']))
        ->toThrow(InvalidArgumentException::class, 'Unknown timezone [Mars/Olympus_Mons].')
        ->and(fn () => Organization::factory()->create(['timezone' => '']))
        ->toThrow(InvalidArgumentException::class);

    expect($organization->fresh()->timezone)->toBe('Europe/Madrid');
});
