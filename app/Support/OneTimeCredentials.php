<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * A generated password shown to the superadmin exactly once.
 *
 * The POST that makes the password redirects. The password crosses that
 * redirect in the session, encrypted with the app key, so the session store
 * never holds it in plain text. The next page pulls it, decrypts it, and
 * hands it to Inertia as flash data: it is sent in that one response, is
 * not kept in browser history, and is gone on the next visit. The password
 * is never logged and only its hash is saved on the user.
 */
class OneTimeCredentials
{
    private const SESSION_KEY = 'admin.one_time_credentials';

    /** The Inertia flash key pages read the credentials from. */
    public const FLASH_KEY = 'credentials';

    public const PASSWORD_LENGTH = 16;

    /**
     * A random password with letters, numbers, and symbols.
     */
    public static function generatePassword(): string
    {
        return Str::password(self::PASSWORD_LENGTH);
    }

    /**
     * Keep the credentials, encrypted, for the next request only.
     */
    public static function put(Request $request, string $email, string $password): void
    {
        $request->session()->flash(self::SESSION_KEY, Crypt::encrypt([
            'email' => $email,
            'password' => $password,
        ]));
    }

    /**
     * Move stored credentials into this response's Inertia flash data. Call
     * it from the page the POST redirects to, right before rendering.
     */
    public static function release(Request $request): void
    {
        $encrypted = $request->session()->pull(self::SESSION_KEY);

        if (! is_string($encrypted)) {
            return;
        }

        try {
            $credentials = Crypt::decrypt($encrypted);
        } catch (DecryptException) {
            return;
        }

        if (is_array($credentials)) {
            Inertia::flash(self::FLASH_KEY, [
                'email' => (string) ($credentials['email'] ?? ''),
                'password' => (string) ($credentials['password'] ?? ''),
            ]);
        }
    }
}
