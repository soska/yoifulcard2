<?php

use App\Enums\CardStatus;
use App\Models\Card;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The card codes on the list page, in order, for the given query string.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function listedCodes(mixed $test, array $query): array
{
    $codes = [];

    $test->get(route('cards.index', $query))
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$codes) {
            $page->component('cards/index');
            $codes = array_column($page->toArray()['props']['cards']['data'], 'code');
        });

    return $codes;
}

test('cards list sorts by each column in both directions with never-used cards last', function () {
    [$user, , $program] = cardOwner();
    $now = Carbon::parse('2026-09-01 12:00:00');

    // code, balance, created, last used
    $rows = [
        ['YGFT-BBBB', '50.00', 3, 1],
        ['YGFT-AAAA', '5.00', 1, null],
        ['YGFT-DDDD', '500.00', 2, 3],
        ['YGFT-CCCC', '0.00', 4, null],
        ['YGFT-EEEE', '75.50', 5, 2],
    ];

    foreach ($rows as [$code, $balance, $createdDays, $usedDays]) {
        Card::factory()->for($program)->create([
            'code' => $code,
            'balance' => $balance,
            'created_at' => $now->copy()->addDays($createdDays),
            'last_used_at' => $usedDays === null ? null : $now->copy()->addDays(10 + $usedDays),
        ]);
    }

    $this->actingAs($user);

    // Default: newest first.
    expect(listedCodes($this, []))->toBe(['YGFT-EEEE', 'YGFT-CCCC', 'YGFT-BBBB', 'YGFT-DDDD', 'YGFT-AAAA']);

    $expected = [
        'code' => ['YGFT-AAAA', 'YGFT-BBBB', 'YGFT-CCCC', 'YGFT-DDDD', 'YGFT-EEEE'],
        'balance' => ['YGFT-CCCC', 'YGFT-AAAA', 'YGFT-BBBB', 'YGFT-EEEE', 'YGFT-DDDD'],
        'created_at' => ['YGFT-AAAA', 'YGFT-DDDD', 'YGFT-BBBB', 'YGFT-CCCC', 'YGFT-EEEE'],
    ];

    foreach ($expected as $sort => $ascending) {
        expect(listedCodes($this, ['sort' => $sort, 'direction' => 'asc']))->toBe($ascending, "{$sort} asc")
            ->and(listedCodes($this, ['sort' => $sort, 'direction' => 'desc']))->toBe(array_reverse($ascending), "{$sort} desc");
    }

    $asc = listedCodes($this, ['sort' => 'last_used_at', 'direction' => 'asc']);
    $desc = listedCodes($this, ['sort' => 'last_used_at', 'direction' => 'desc']);

    expect(array_slice($asc, 0, 3))->toBe(['YGFT-BBBB', 'YGFT-EEEE', 'YGFT-DDDD'])
        ->and(array_slice($desc, 0, 3))->toBe(['YGFT-DDDD', 'YGFT-EEEE', 'YGFT-BBBB'])
        ->and(array_slice($asc, 3))->toEqualCanonicalizing(['YGFT-AAAA', 'YGFT-CCCC'])
        ->and(array_slice($desc, 3))->toEqualCanonicalizing(['YGFT-AAAA', 'YGFT-CCCC']);

    // Unknown sort values fall back to the default.
    $this->get(route('cards.index', ['sort' => 'qr_token', 'direction' => 'sideways']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc'));
});

test('cards list filters by status and searches code and email', function () {
    [$user, , $program] = cardOwner();

    Card::factory()->for($program)->create(['code' => 'YGFT-AB12', 'email' => 'maria@example.com']);
    Card::factory()->for($program)->frozen()->create(['code' => 'YGFT-CD34', 'email' => 'JUAN@Example.com']);
    Card::factory()->for($program)->create(['code' => 'YGFT-EF56', 'email' => null, 'status' => CardStatus::Depleted]);
    Card::factory()->for($program)->frozen()->create(['code' => 'YGFT-GH78', 'email' => 'ab12@shop.test']);
    // Another organization's card never shows up.
    Card::factory()->create(['code' => 'YGFT-AB99', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    expect(listedCodes($this, ['status' => 'frozen', 'sort' => 'code', 'direction' => 'asc']))->toBe(['YGFT-CD34', 'YGFT-GH78'])
        ->and(listedCodes($this, ['status' => 'depleted']))->toBe(['YGFT-EF56'])
        // Case-insensitive, partial, on code or email.
        ->and(listedCodes($this, ['q' => 'ab12', 'sort' => 'code', 'direction' => 'asc']))->toBe(['YGFT-AB12', 'YGFT-GH78'])
        ->and(listedCodes($this, ['q' => 'juan@EXAMPLE']))->toBe(['YGFT-CD34'])
        ->and(listedCodes($this, ['q' => 'maria']))->toBe(['YGFT-AB12'])
        // Search and filter combine.
        ->and(listedCodes($this, ['q' => 'ab12', 'status' => 'frozen']))->toBe(['YGFT-GH78'])
        // LIKE wildcards are matched literally.
        ->and(listedCodes($this, ['q' => '%']))->toBe([])
        ->and(listedCodes($this, ['q' => '_']))->toBe([]);

    $this->get(route('cards.index', ['status' => 'frozen', 'q' => ' ab12 ']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'frozen')
            ->where('filters.q', 'ab12'));
});

test('cards list paginates at 20 and keeps query string', function () {
    [$user, , $program] = cardOwner();
    Card::factory()->count(45)->for($program)->create(['email' => 'shop@example.com']);
    Card::factory()->count(3)->for($program)->frozen()->create(['email' => 'shop@example.com']);

    $this->actingAs($user)
        ->get(route('cards.index', ['status' => 'active', 'q' => 'shop', 'sort' => 'code', 'direction' => 'asc']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cards.data', 20)
            ->where('cards.total', 45)
            ->where('cards.per_page', 20)
            ->where('cards.current_page', 1)
            ->where('cards.last_page', 3)
            ->where('cards.next_page_url', fn (string $url) => str_contains($url, 'status=active')
                && str_contains($url, 'q=shop')
                && str_contains($url, 'sort=code')
                && str_contains($url, 'direction=asc')
                && str_contains($url, 'page=2')));

    $this->actingAs($user)
        ->get(route('cards.index', ['status' => 'active', 'q' => 'shop', 'page' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cards.data', 5)
            ->where('cards.current_page', 3)
            ->where('cards.prev_page_url', fn (string $url) => str_contains($url, 'status=active')
                && str_contains($url, 'q=shop')
                && str_contains($url, 'page=2')));
});

test('qr_token never appears in list or detail props', function () {
    [$user, , $program] = cardOwner();
    $cards = Card::factory()->count(3)->for($program)->create(['email' => 'holder@example.com']);
    $card = $cards->first();

    $list = $this->actingAs($user)->get(route('cards.index'));
    $detail = $this->actingAs($user)->get(route('cards.show', $card));
    $create = $this->actingAs($user)->get(route('cards.create'));

    foreach ([$list, $detail, $create] as $response) {
        $response->assertOk();
        $body = $response->getContent();

        expect($body)->not->toContain('qr_token');

        foreach ($cards as $each) {
            expect($body)->not->toContain($each->qr_token);
        }
    }

    // Inertia visits return JSON; the token is not there either.
    $version = $this->get(route('cards.show', $card))->viewData('page')['version'];

    $json = $this->actingAs($user)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version])
        ->get(route('cards.show', $card))
        ->assertOk()
        ->json();

    expect(json_encode($json))->not->toContain($card->qr_token)
        ->and($json['props']['card'])->not->toHaveKey('qr_token')
        ->and($card->toArray())->not->toHaveKey('qr_token');
});
