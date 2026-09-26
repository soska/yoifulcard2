<?php

namespace App\Support;

use App\Services\CardCodeGenerator;

/**
 * Reads the card token out of what the reader's camera scanned. A card QR
 * encodes `{APP_URL}/c/{token}` (see Card::qrPayload()). The host is not
 * checked, so cards printed from another URL of the same app still scan;
 * the token lookup is limited to the current organization anyway.
 */
class QrPayload
{
    /** Longest payload worth parsing. A real one is well under this. */
    public const MAX_LENGTH = 2048;

    /**
     * The token in the payload, or null when the payload is not a Yoiful
     * card URL.
     */
    public static function token(mixed $payload): ?string
    {
        if (! is_string($payload)) {
            return null;
        }

        $payload = trim($payload);

        if ($payload === '' || strlen($payload) > self::MAX_LENGTH) {
            return null;
        }

        $parts = parse_url($payload);

        if (! is_array($parts)
            || ! in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            || ($parts['host'] ?? '') === ''
            || ! isset($parts['path'])) {
            return null;
        }

        $pattern = sprintf('#^/c/([A-Za-z0-9_-]{%d})/?$#', CardCodeGenerator::TOKEN_LENGTH);

        return preg_match($pattern, $parts['path'], $matches) === 1 ? $matches[1] : null;
    }
}
