<?php

use App\Models\Organization;
use App\Models\Program;
use App\Models\Superadmin;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * A user who is the owner of a new organization with one program.
 *
 * @param  array<string, mixed>  $organization  Attributes for the organization.
 * @return array{0: User, 1: Organization, 2: Program}
 */
function cardOwner(array $organization = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user)->create($organization);
    $program = Program::factory()->for($organization)->create();

    return [$user, $organization, $program];
}

/**
 * A signed-up user with the superadmin role.
 */
function superadmin(): User
{
    $user = User::factory()->create();
    Superadmin::factory()->for($user)->create();

    return $user;
}

/**
 * What the browser shows for an English UI message in `$locale`, looked up
 * the way the duckalization runtime does it: `en.meta.json` gives the id of
 * the message (with its context), and the locale's catalog holds the text.
 * Null when the catalog has no entry (the browser would show the English).
 */
function catalogLine(string $locale, string $message, ?string $context = null): ?string
{
    $read = fn (string $file): array => json_decode(
        file_get_contents(resource_path('js/locales/'.$file)),
        true,
        flags: JSON_THROW_ON_ERROR,
    );

    $catalog = $read($locale.'.json');

    foreach ($read('en.meta.json') as $id => $entry) {
        if ($entry['message'] === $message && ($entry['context'] ?? null) === $context) {
            return $catalog[$id] ?? null;
        }
    }

    return null;
}
