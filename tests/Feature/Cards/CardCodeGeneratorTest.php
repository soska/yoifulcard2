<?php

use App\Models\Card;
use App\Services\CardCodeGenerator;

test('card code matches YGFT-XXXX and qr token is 64 url-safe characters', function () {
    $generator = new CardCodeGenerator;

    foreach (range(1, 25) as $ignored) {
        expect($generator->code())->toMatch('/^YGFT-[A-Z0-9]{4}$/')
            ->and($generator->qrToken())->toMatch('/^[A-Za-z0-9_-]{64}$/');
    }
});

test('generator retries on collision', function () {
    Card::factory()->create(['code' => 'YGFT-AAAA', 'qr_token' => str_repeat('a', 64)]);
    Card::factory()->create(['code' => 'YGFT-BBBB', 'qr_token' => str_repeat('b', 64)]);

    $calls = 0;
    $candidates = ['AAAA', 'BBBB', 'CCCC', str_repeat('a', 64), str_repeat('b', 64), str_repeat('c', 64)];
    $generator = new CardCodeGenerator(function (string $alphabet, int $length) use (&$calls, $candidates): string {
        return $candidates[$calls++];
    });

    expect($generator->code())->toBe('YGFT-CCCC')
        ->and($generator->qrToken())->toBe(str_repeat('c', 64))
        ->and($calls)->toBe(6);
});

test('generator fails loudly after ten collisions', function () {
    Card::factory()->create(['code' => 'YGFT-AAAA']);

    $calls = 0;
    $generator = new CardCodeGenerator(function () use (&$calls): string {
        $calls++;

        return 'AAAA';
    });

    expect(fn () => $generator->code())->toThrow(RuntimeException::class);
    expect($calls)->toBe(CardCodeGenerator::MAX_ATTEMPTS);
});
