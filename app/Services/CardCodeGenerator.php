<?php

namespace App\Services;

use App\Models\Card;
use Closure;
use RuntimeException;

/**
 * Makes the public card code (YGFT-XXXXXX) and the QR token. Both must be
 * unique, so a candidate that is already taken is replaced with a new one,
 * up to MAX_ATTEMPTS times.
 */
class CardCodeGenerator
{
    public const MAX_ATTEMPTS = 10;

    public const CODE_PREFIX = 'YGFT-';

    /**
     * Codes are printed on cards and typed by people, so the alphabet leaves
     * out characters that look alike: 0, O, 1, I and L. 31 characters to the
     * power of CODE_LENGTH is about 890 million codes.
     */
    public const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public const CODE_LENGTH = 6;

    public const TOKEN_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_';

    public const TOKEN_LENGTH = 64;

    /**
     * @var Closure(string, int): string
     */
    private Closure $random;

    /**
     * @param  (Closure(string, int): string)|null  $random  Returns a string of the given length drawn from the alphabet. Tests pass their own.
     */
    public function __construct(?Closure $random = null)
    {
        $this->random = $random ?? self::secureRandom(...);
    }

    public function code(): string
    {
        return $this->unique('code', fn () => self::CODE_PREFIX.($this->random)(self::CODE_ALPHABET, self::CODE_LENGTH));
    }

    public function qrToken(): string
    {
        return $this->unique('qr_token', fn () => ($this->random)(self::TOKEN_ALPHABET, self::TOKEN_LENGTH));
    }

    /**
     * @param  Closure(): string  $candidate
     */
    private function unique(string $column, Closure $candidate): string
    {
        for ($attempt = 0; $attempt < self::MAX_ATTEMPTS; $attempt++) {
            $value = $candidate();

            if (! Card::query()->where($column, $value)->exists()) {
                return $value;
            }
        }

        throw new RuntimeException("Could not generate a unique card {$column} after ".self::MAX_ATTEMPTS.' attempts.');
    }

    private static function secureRandom(string $alphabet, int $length): string
    {
        $max = strlen($alphabet) - 1;
        $value = '';

        for ($i = 0; $i < $length; $i++) {
            $value .= $alphabet[random_int(0, $max)];
        }

        return $value;
    }
}
