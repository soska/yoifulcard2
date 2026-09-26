<?php

use App\Models\User;
use App\Support\PackageMessages;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Http\Requests\LoginRequest;
use Laravel\Fortify\Http\Requests\TwoFactorLoginRequest;
use Laravel\Passkeys\Http\Requests\PasskeyRegistrationRequest;
use Laravel\Passkeys\Http\Requests\PasskeyVerificationRequest;

use function Pest\Laravel\artisan;

/*
|--------------------------------------------------------------------------
| One catalog, two runtimes (adapted from Multiplano)
|--------------------------------------------------------------------------
|
| duckalization is TypeScript tooling, but the server says things too:
| validation refusals, ledger errors, the CSV header, the offline page. So
| `php artisan i18n:manifest` declares Laravel's `__('…')` literals to the
| extractor, they are translated with the rest of the catalog, and
| `scripts/build-php-lang.mjs` writes them back as lang/es.json.
|
| The bridge rests on one fact: Laravel keys JSON translations by the ENGLISH
| text, and duckalization's en.meta.json maps every id to that same English.
|
*/

function phpManifest(): string
{
    return File::get(resource_path('js/locales/php-messages.generated.ts'));
}

/**
 * @return array<string, string>
 */
function phpSpanish(): array
{
    return json_decode(File::get(lang_path('es.json')), true, flags: JSON_THROW_ON_ERROR);
}

test('the manifest declares every literal message and no lang keys', function () {
    artisan('i18n:manifest')->assertSuccessful();

    $manifest = phpManifest();

    // Real server messages: a ledger refusal, a validation message, the CSV
    // header, a transaction type, and a Blade view's text.
    expect($manifest)
        ->toContain("__('Insufficient balance.')")
        ->toContain("__('The balance cannot be more than :max.')")
        ->toContain("__('Use only lowercase letters, numbers, and single hyphens.')")
        ->toContain("__('Balance after')")
        ->toContain("__('Charge')")
        ->toContain("__('You are offline')")
        ->toContain("__(':name\\'s Business')")
        // Package sentences, declared through App\Support\PackageMessages.
        ->toContain("__('Passkey registration session expired. Please try again.')")
        // A framework lang KEY is not a message.
        ->not->toContain("__('auth.failed')")
        // A runtime value has no text at the call site to translate.
        ->not->toContain('$');
});

test('the manifest ignores strings that only appear in comments', function () {
    artisan('i18n:manifest')->assertSuccessful();

    // BuildI18nManifest's and LedgerException's docblocks both write
    // `__('…')`. The command tokenizes PHP, so neither is declared.
    expect(phpManifest())->not->toContain("__('…')");
});

test('the manifest is committed and current', function () {
    artisan('i18n:manifest --check')->assertSuccessful();
});

test('lang/es.json is built from the shared catalog', function () {
    $result = Process::path(base_path())->run(['node', 'scripts/build-php-lang.mjs', '--check']);

    expect($result->successful())->toBeTrue($result->errorOutput().$result->output());

    $translations = phpSpanish();

    expect($translations)->not->toBeEmpty()
        // Each line is what the browser catalog says for the same English.
        ->and($translations['Insufficient balance.'])->toBe(catalogLine('es', 'Insufficient balance.'))
        // Only the server's messages, not every button in the UI.
        ->and($translations)->not->toHaveKey('Log in');

    foreach ($translations as $english => $spanish) {
        expect(phpManifest())->toContain("__('".str_replace("'", "\\'", $english)."')");
        expect($spanish)->toBeString()->not->toBe('');
    }
});

test('Laravel renders the Spanish the shared catalog produced', function () {
    $translations = phpSpanish();
    $english = array_key_first($translations);

    App::setLocale('es');
    expect(__($english))->toBe($translations[$english]);

    App::setLocale('en');
    expect(__($english))->toBe($english);
});

test('an untranslated string falls back to English rather than blanking out', function () {
    App::setLocale('es');

    expect(__('An untranslated sentence that is in no catalog.'))
        ->toBe('An untranslated sentence that is in no catalog.');
});

test('a placeholder survives translation', function () {
    App::setLocale('es');

    expect(__('The balance cannot be more than :max.', ['max' => '10000.00']))
        ->toBe('El saldo no puede ser mayor a 10000.00.')
        ->and(__(':name\'s Business', ['name' => 'Ana']))
        ->toBe('Negocio de Ana');
});

test('package sentences in the catalog still exist in their packages', function () {
    // PackageMessages repeats sentences Fortify and laravel/passkeys render.
    // If an update rewords one, its Spanish silently stops matching.
    $sources = collect(PackageMessages::SOURCES)
        ->map(fn (string $path) => File::get(base_path('vendor/'.$path)))
        ->implode("\n");

    App::setLocale('en');

    foreach (PackageMessages::messages() as $message) {
        expect($sources)->toContain("__('{$message}')");
        expect(phpSpanish())->toHaveKey($message);
    }
});

/*
|--------------------------------------------------------------------------
| The framework's own strings
|--------------------------------------------------------------------------
|
| lang/es/*.php comes from laravel-lang/common (`php artisan lang:add es`),
| edited from usted to tú, with this app's field names in `attributes`.
|
*/

test('the framework speaks Spanish too', function () {
    App::setLocale('es');

    $validator = Validator::make([], ['email' => 'required']);
    $validator->fails();

    expect($validator->errors()->first('email'))->toBe('El campo correo electrónico es obligatorio.')
        ->and(__('auth.failed'))->not->toBe('auth.failed')
        ->and(__('passwords.sent'))->toStartWith('Te hemos enviado');
});

test('framework spanish uses tú', function () {
    // laravel-lang's Spanish says USTED. Yoiful says TÚ, so the files were
    // edited after publishing, and `lang:update` would quietly undo that.
    $suspect = [
        '/\busted(es)?\b/iu',
        '/\b(Su|Sus)\s/u',
        '/\bLe hemos\b/u',
        '/\b(intente|espere|elija|haga|ingrese|seleccione|verifique|introduzca|compruebe)\b/iu',
    ];

    $files = glob(lang_path('es/*.php'));
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $strings = require $file;

        array_walk_recursive($strings, function ($value) use ($suspect, $file) {
            foreach ($suspect as $pattern) {
                expect(preg_match($pattern, (string) $value))
                    ->toBe(0, basename($file).' has an usted form: '.$value);
            }
        });
    }

    // And the edits themselves are there.
    App::setLocale('es');
    expect(__('passwords.reset'))->toBe('Tu contraseña ha sido restablecida.')
        ->and(__('auth.throttle', ['seconds' => 30]))->toContain('inténtalo');
});

test('every validation field has a spanish attribute name', function () {
    // Laravel humanizes an unnamed field from its key, so `initial_balance`
    // would read "El campo initial balance es obligatorio." in Spanish.
    $named = trans('validation.attributes', [], 'es');
    $user = User::factory()->create();

    $unnamed = [];

    foreach (validatedFields($user) as $field => $where) {
        if (! is_string($named[$field] ?? null) || $named[$field] === '') {
            $unnamed[] = "{$field} ({$where})";
        }
    }

    expect($unnamed)->toBe([], 'no Spanish name in lang/es/validation.php for: '.implode(', ', $unnamed));
});

test('every inline validator is covered by the attribute sweep', function () {
    // The sweep reads form requests by reflection. Validation written inline
    // is listed by hand in validatedFields(); a new inline validator must be
    // added there, or its fields could go unnamed.
    $inline = collect(File::allFiles(app_path()))
        ->filter(fn ($file) => preg_match('/->validate\(\s*\[|Validator::make\(/', $file->getContents()) === 1)
        ->map(fn ($file) => $file->getRelativePathname())
        ->sort()
        ->values()
        ->all();

    expect($inline)->toBe([
        'Actions/Fortify/CreateNewUser.php',
        'Http/Controllers/Preferences/ThemeController.php',
    ]);
});

/**
 * Every input field a person can fail validation on, with where it is
 * validated. Dotted keys (`credential.id`) are parts of a payload the
 * browser builds, not fields a person fills in, and are left out.
 *
 * @return array<string, string> field => class
 */
function validatedFields(User $user): array
{
    $classes = collect(File::allFiles(app_path('Http/Requests')))
        ->map(fn ($file) => 'App\\Http\\Requests\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname()))
        ->filter(fn (string $class) => is_subclass_of($class, FormRequest::class))
        ->merge([
            // Package forms this app shows.
            LoginRequest::class,
            TwoFactorLoginRequest::class,
            PasskeyRegistrationRequest::class,
            PasskeyVerificationRequest::class,
        ]);

    $fields = [];

    foreach ($classes as $class) {
        /** @var FormRequest $request */
        $request = $class::create('/', 'POST');
        $request->setContainer(app())->setUserResolver(fn () => $user);

        foreach (array_keys($request->rules()) as $field) {
            $fields[$field] ??= class_basename($class);
        }
    }

    // Inline validators (see the test above), and fields validated by
    // Fortify without a form request (password confirmation, the `confirmed`
    // rule's twin field).
    $inline = [
        'name' => 'CreateNewUser',
        'email' => 'CreateNewUser',
        'password' => 'CreateNewUser',
        'password_confirmation' => 'CreateNewUser',
        'theme' => 'ThemeController',
        'locale' => 'LocaleController',
    ];

    foreach ($inline as $field => $where) {
        $fields[$field] ??= $where;
    }

    return collect($fields)
        ->reject(fn (string $where, string $field) => str_contains($field, '.'))
        ->all();
}
