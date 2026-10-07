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
        ['YGFT-BBBBBB', '50.00', 3, 1],
        ['YGFT-AAAAAA', '5.00', 1, null],
        ['YGFT-DDDDDD', '500.00', 2, 3],
        ['YGFT-CCCCCC', '0.00', 4, null],
        ['YGFT-EEEEEE', '75.50', 5, 2],
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
    expect(listedCodes($this, []))->toBe(['YGFT-EEEEEE', 'YGFT-CCCCCC', 'YGFT-BBBBBB', 'YGFT-DDDDDD', 'YGFT-AAAAAA']);

    $expected = [
        'code' => ['YGFT-AAAAAA', 'YGFT-BBBBBB', 'YGFT-CCCCCC', 'YGFT-DDDDDD', 'YGFT-EEEEEE'],
        'balance' => ['YGFT-CCCCCC', 'YGFT-AAAAAA', 'YGFT-BBBBBB', 'YGFT-EEEEEE', 'YGFT-DDDDDD'],
        'created_at' => ['YGFT-AAAAAA', 'YGFT-DDDDDD', 'YGFT-BBBBBB', 'YGFT-CCCCCC', 'YGFT-EEEEEE'],
    ];

    foreach ($expected as $sort => $ascending) {
        expect(listedCodes($this, ['sort' => $sort, 'direction' => 'asc']))->toBe($ascending, "{$sort} asc")
            ->and(listedCodes($this, ['sort' => $sort, 'direction' => 'desc']))->toBe(array_reverse($ascending), "{$sort} desc");
    }

    $asc = listedCodes($this, ['sort' => 'last_used_at', 'direction' => 'asc']);
    $desc = listedCodes($this, ['sort' => 'last_used_at', 'direction' => 'desc']);

    expect(array_slice($asc, 0, 3))->toBe(['YGFT-BBBBBB', 'YGFT-EEEEEE', 'YGFT-DDDDDD'])
        ->and(array_slice($desc, 0, 3))->toBe(['YGFT-DDDDDD', 'YGFT-EEEEEE', 'YGFT-BBBBBB'])
        ->and(array_slice($asc, 3))->toEqualCanonicalizing(['YGFT-AAAAAA', 'YGFT-CCCCCC'])
        ->and(array_slice($desc, 3))->toEqualCanonicalizing(['YGFT-AAAAAA', 'YGFT-CCCCCC']);

    // Unknown sort values fall back to the default.
    $this->get(route('cards.index', ['sort' => 'qr_token', 'direction' => 'sideways']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc'));
});

test('cards list filters by status and searches code and email', function () {
    [$user, , $program] = cardOwner();

    Card::factory()->for($program)->create(['code' => 'YGFT-AB12AB', 'email' => 'maria@example.com']);
    Card::factory()->for($program)->frozen()->create(['code' => 'YGFT-CD34CD', 'email' => 'JUAN@Example.com']);
    Card::factory()->for($program)->create(['code' => 'YGFT-EF56EF', 'email' => null, 'status' => CardStatus::Depleted]);
    Card::factory()->for($program)->frozen()->create(['code' => 'YGFT-GH78GH', 'email' => 'ab12@shop.test']);
    // Another organization's card never shows up.
    Card::factory()->create(['code' => 'YGFT-AB99AB', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    expect(listedCodes($this, ['status' => 'frozen', 'sort' => 'code', 'direction' => 'asc']))->toBe(['YGFT-CD34CD', 'YGFT-GH78GH'])
        ->and(listedCodes($this, ['status' => 'depleted']))->toBe(['YGFT-EF56EF'])
        // Case-insensitive, partial, on code or email.
        ->and(listedCodes($this, ['q' => 'ab12', 'sort' => 'code', 'direction' => 'asc']))->toBe(['YGFT-AB12AB', 'YGFT-GH78GH'])
        ->and(listedCodes($this, ['q' => 'juan@EXAMPLE']))->toBe(['YGFT-CD34CD'])
        ->and(listedCodes($this, ['q' => 'maria']))->toBe(['YGFT-AB12AB'])
        // Search and filter combine.
        ->and(listedCodes($this, ['q' => 'ab12', 'status' => 'frozen']))->toBe(['YGFT-GH78GH'])
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

test('issued and inventory views partition cards and preserve filtering', function () {
    [$user, , $program] = cardOwner();
    Card::factory()->for($program)->create(['code' => 'YGFT-ISSUED', 'email' => 'customer@example.com']);
    Card::factory()->for($program)->create(['code' => 'YGFT-STOCK1', 'status' => CardStatus::Inactive]);
    Card::factory()->create(['status' => CardStatus::Inactive]);
    $this->actingAs($user);

    expect(listedCodes($this, []))->toBe(['YGFT-ISSUED'])
        ->and(listedCodes($this, ['view' => 'inventory']))->toBe(['YGFT-STOCK1'])
        ->and(listedCodes($this, ['status' => 'inactive']))->toBe(['YGFT-STOCK1'])
        ->and(listedCodes($this, ['view' => 'issued', 'status' => 'inactive']))->toBe(['YGFT-ISSUED'])
        ->and(listedCodes($this, ['view' => 'inventory', 'q' => 'ISSUED']))->toBe([])
        ->and(listedCodes($this, ['view' => 'unknown']))->toBe(['YGFT-ISSUED']);

    Card::factory()->count(21)->for($program)->create(['status' => CardStatus::Inactive]);
    $this->get(route('cards.index', ['view' => 'inventory']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.view', 'inventory')
            ->where('cards.total', 22)
            ->where('cards.next_page_url', fn (string $url) => str_contains($url, 'view=inventory')));
});
