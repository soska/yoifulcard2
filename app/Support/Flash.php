<?php

namespace App\Support;

use App\Enums\FlashMessage;
use Inertia\Inertia;

/**
 * Flashes a toast as a code plus the params its sentence needs. The words are
 * in resources/js/lib/flash.ts (see App\Enums\FlashMessage).
 *
 * The flashed value is a plain array of strings: it goes through the session,
 * and params are stringified here, where they enter it.
 */
final class Flash
{
    /**
     * @param  array<string, string|int|float|\Stringable>  $params
     */
    public static function success(FlashMessage $message, array $params = []): void
    {
        self::toast('success', $message, $params);
    }

    /**
     * @param  array<string, string|int|float|\Stringable>  $params
     */
    public static function error(FlashMessage $message, array $params = []): void
    {
        self::toast('error', $message, $params);
    }

    /**
     * @param  'success'|'error'  $type
     * @param  array<string, string|int|float|\Stringable>  $params
     * @return array{type: string, code: string, params: array<string, string>}
     */
    public static function of(string $type, FlashMessage $message, array $params = []): array
    {
        return [
            'type' => $type,
            'code' => $message->value,
            'params' => array_map(
                static fn (string|int|float|\Stringable $value): string => (string) $value,
                $params,
            ),
        ];
    }

    /**
     * @param  'success'|'error'  $type
     * @param  array<string, string|int|float|\Stringable>  $params
     */
    private static function toast(string $type, FlashMessage $message, array $params): void
    {
        Inertia::flash('toast', self::of($type, $message, $params));
    }
}
