<?php

namespace App\Support;

/**
 * Sentences that Composer packages render with Laravel's JSON translator
 * (`__('…')` keyed by the English), repeated here as literals so
 * `php artisan i18n:manifest` declares them to the shared duckalization
 * catalog and `npm run i18n:lang` writes their Spanish into lang/es.json.
 *
 * Nothing calls messages(). It exists only to be read by the manifest. When a
 * package update changes one of these sentences, change it here too (a test in
 * tests/Feature/Locale/I18nBridgeTest.php checks each one is still in vendor/).
 *
 * The framework's own strings (validation rules, auth, passwords, pagination)
 * are not here: they are lang KEYS answered by lang/es/*.php, from
 * laravel-lang/common.
 */
final class PackageMessages
{
    /**
     * The package that renders each sentence, relative to vendor/.
     */
    public const SOURCES = [
        'laravel/fortify/src/Http/Responses/FailedTwoFactorLoginResponse.php',
        'laravel/fortify/src/Http/Responses/FailedPasswordConfirmationResponse.php',
        'laravel/passkeys/src/Http/Requests/PasskeyRegistrationRequest.php',
        'laravel/passkeys/src/Http/Requests/PasskeyVerificationRequest.php',
    ];

    /**
     * @return list<string>
     */
    public static function messages(): array
    {
        return [
            // Fortify: two-factor challenge and password confirmation.
            __('The provided two factor authentication code was invalid.'),
            __('The provided two factor recovery code was invalid.'),
            __('The provided password was incorrect.'),
            // laravel/passkeys: registering and signing in with a passkey.
            __('Invalid credential format.'),
            __('Passkey registration session expired. Please try again.'),
            __('Passkey verification session expired. Please try again.'),
        ];
    }
}
