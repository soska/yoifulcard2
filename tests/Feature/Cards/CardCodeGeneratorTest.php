<?php

use App\Models\Card;
use App\Services\CardCodeGenerator;

test('card code matches YGFT-XXXXXX and qr token is 64 url-safe characters', function () {
    $generator = new CardCodeGenerator;

    foreach (range(1, 25) as $ignored) {
        expect($generator->code())->toMatch('/^YGFT-[A-HJKMNP-Z2-9]{6}$/')
            ->and($generator->qrToken())->toMatch('/^[A-Za-z0-9_-]{64}$/');
    }
});

test('card codes leave out characters that look alike', function () {
    expect(CardCodeGenerator::CODE_ALPHABET)->not->toMatch('/[01OIL]/')
        ->and(strlen(CardCodeGenerator::CODE_ALPHABET))->toBe(31)
        ->and(strlen(count_chars(CardCodeGenerator::CODE_ALPHABET, 3)))->toBe(31);
});

test('generator retries on collision', function () {
    Card::factory()->create(['code' => 'YGFT-AAAAAA', 'qr_token' => str_repeat('a', 64)]);
    Card::factory()->create(['code' => 'YGFT-BBBBBB', 'qr_token' => str_repeat('b', 64)]);

    $calls = 0;
    $candidates = ['AAAAAA', 'BBBBBB', 'CCCCCC', str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64)];
    $generator = new CardCodeGenerator(function (string $alphabet, int $length) use (&$calls, $candidates): string {
        return $candidates[$calls++];
    });

    expect($generator->code())->toBe('YGFT-CCCCCC')
        ->and($generator->qrToken())->toBe(str_repeat('c', 64))
        ->and($calls)->toBe(6);
});

test('generator fails loudly after ten collisions', function () {
    Card::factory()->create(['code' => 'YGFT-AAAAAA']);

    $calls = 0;
    $generator = new CardCodeGenerator(function () use (&$calls): string {
        $calls++;

        return 'AAAAAA';
    });

    expect(fn () => $generator->code())->toThrow(RuntimeException::class);
    expect($calls)->toBe(CardCodeGenerator::MAX_ATTEMPTS);
});
