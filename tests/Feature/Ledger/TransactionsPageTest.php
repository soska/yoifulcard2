<?php

use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * An owner with two cards and five transactions spread over September 2026,
 * plus another organization's transaction that must never show up. The times
 * are the organization's local times.
 *
 * @return array{0: User, 1: Organization, 2: array<string, Transaction>}
 */
function transactionHistory(): array
{
    [$user, $organization] = cardOwner();
    $alpha = Card::factory()->forOrganization($organization)->create(['code' => 'YGFT-AAA1']);
    $beta = Card::factory()->forOrganization($organization)->create(['code' => 'YGFT-BBB2']);

    $make = fn (Card $card, string $type, string $at, string $amount, ?string $note = null) => Transaction::factory()
        ->for($card)
        ->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => '100.00',
            'note' => $note,
            'performed_by' => $user->id,
            'created_at' => Carbon::parse($at, $organization->timezone)->utc(),
        ]);

    $rows = [
        'alphaLoad' => $make($alpha, 'load', '2026-09-01 09:00:00', '100.00'),
        'alphaSpend' => $make($alpha, 'spend', '2026-09-10 12:00:00', '20.00'),
        'betaLoad' => $make($beta, 'load', '2026-09-15 23:59:59', '50.00'),
        'betaAdjust' => $make($beta, 'adjustment', '2026-09-16 00:00:00', '-5.00', '=SUM(A1) typo'),
        'alphaSpendLate' => $make($alpha, 'spend', '2026-09-30 18:00:00', '10.00'),
    ];

    // Another organization's card, with a code that matches every search.
    [, $other] = cardOwner();
    $foreign = Card::factory()->forOrganization($other)->create(['code' => 'YGFT-AAA9']);
    Transaction::factory()->for($foreign)->create(['type' => 'load', 'created_at' => Carbon::parse('2026-09-10 12:00:00')]);

    return [$user, $organization, $rows];
}

/**
 * The transaction ids a transactions page shows, in order.
 *
 * @param  array<string, string>  $query
 * @return list<string>
 */
function listedTransactionIds(TestResponse $response): array
{
    $ids = [];

    $response->assertInertia(function (Assert $page) use (&$ids): void {
        $page->component('transactions/index');
        $ids = array_column($page->toArray()['props']['transactions']['data'], 'id');
    });

    return $ids;
}

test('transactions filter by type, date range, and card code', function () {
    [$user, , $rows] = transactionHistory();
    $this->actingAs($user);

    $ids = fn (array $query) => listedTransactionIds($this->get(route('transactions.index', $query)));
    $expected = fn (string ...$keys) => array_map(fn (string $key) => $rows[$key]->id, $keys);

    // No filters: the organization's rows only, newest first.
    expect($ids([]))->toBe($expected('alphaSpendLate', 'betaAdjust', 'betaLoad', 'alphaSpend', 'alphaLoad'));

    expect($ids(['type' => 'spend']))->toBe($expected('alphaSpendLate', 'alphaSpend'))
        ->and($ids(['type' => 'adjustment']))->toBe($expected('betaAdjust'));

    // Both ends of the range are whole days and inclusive.
    expect($ids(['from' => '2026-09-10', 'to' => '2026-09-15']))->toBe($expected('betaLoad', 'alphaSpend'))
        ->and($ids(['from' => '2026-09-16']))->toBe($expected('alphaSpendLate', 'betaAdjust'))
        ->and($ids(['to' => '2026-09-01']))->toBe($expected('alphaLoad'));

    // Card code matches partially and ignores case.
    expect($ids(['card' => 'ygft-aaa']))->toBe($expected('alphaSpendLate', 'alphaSpend', 'alphaLoad'))
        ->and($ids(['card' => 'BBB2']))->toBe($expected('betaAdjust', 'betaLoad'))
        ->and($ids(['card' => 'nothing']))->toBe([]);

    // Filters combine.
    expect($ids(['type' => 'spend', 'card' => 'AAA1', 'from' => '2026-09-11']))->toBe($expected('alphaSpendLate'));

    // The filters come back as props; bad values are dropped.
    $this->get(route('transactions.index', ['type' => 'refund', 'from' => '2026-02-30', 'to' => 'soon', 'card' => 'AAA']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['type' => null, 'from' => null, 'to' => null, 'card' => 'AAA'])
            ->where('types', ['load', 'spend', 'adjustment'])
            ->has('transactions.data', 3)
            ->where('transactions.data.0.card.code', 'YGFT-AAA1')
            ->where('transactions.data.0.performed_by', $user->name)
            ->missing('transactions.data.0.card.qr_token'));
});

test('transactions paginate and keep the query string', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();
    Transaction::factory()->count(30)->for($card)->create(['performed_by' => $user->id]);

    $this->actingAs($user)
        ->get(route('transactions.index', ['type' => 'load', 'page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.current_page', 2)
            ->where('transactions.total', 30)
            ->has('transactions.data', 5)
            ->where('transactions.first_page_url', route('transactions.index', ['type' => 'load', 'page' => 1])));
});

test('csv export matches the filtered list', function () {
    [$user, $organization, $rows] = transactionHistory();
    $this->actingAs($user);

    foreach ([[], ['type' => 'spend'], ['card' => 'bbb', 'from' => '2026-09-15'], ['to' => '2026-09-10']] as $query) {
        $listed = listedTransactionIds($this->get(route('transactions.index', $query)));

        $response = $this->get(route('transactions.export', $query))->assertOk();

        expect($response->headers->get('Content-Type'))->toContain('text/csv')
            ->and($response->headers->get('Content-Disposition'))->toContain('attachment; filename=transactions-');

        $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
        $csv = array_map(fn (string $line) => str_getcsv($line, escape: ''), $lines);

        expect($csv[0])->toBe(['Date', 'Card', 'Type', 'Amount', 'Balance after', 'Note', 'Performed by']);

        $byId = collect($rows)->keyBy('id');
        $expectedRows = array_map(function (string $id) use ($byId, $user, $organization) {
            $transaction = $byId[$id]->load('card');

            return [
                $transaction->created_at->setTimezone($organization->timezone)->toIso8601String(),
                $transaction->card->code,
                $transaction->type->label(),
                $transaction->amount,
                $transaction->balance_after,
                $transaction->note === null ? '' : "'".$transaction->note,
                $user->email,
            ];
        }, $listed);

        expect(array_slice($csv, 1))->toBe($expectedRows);
    }
});

test('transactions page and export need a membership', function () {
    $this->get(route('transactions.index'))->assertRedirect(route('login'));
    $this->get(route('transactions.export'))->assertRedirect(route('login'));
});

test('transaction date filter uses the organization\'s day boundaries', function () {
    [$user, $organization] = cardOwner();
    expect($organization->timezone)->toBe('America/Mexico_City');
    $card = Card::factory()->forOrganization($organization)->create();

    $at = fn (string $utc) => Transaction::factory()->for($card)->create([
        'performed_by' => $user->id,
        'created_at' => Carbon::parse($utc, 'UTC'),
    ]);

    // 23:30 on the 1st in Mexico City (UTC-6) is 05:30 on the 2nd in UTC.
    $lateOnFirst = $at('2026-09-02 05:30:00');
    // 00:30 on the 1st in UTC is still the 31st of August locally.
    $lateOnAugust31 = $at('2026-09-01 00:30:00');
    // 06:00 on the 2nd in UTC is local midnight on the 2nd.
    $midnightOnSecond = $at('2026-09-02 06:00:00');

    $this->actingAs($user);
    $ids = fn (array $query) => listedTransactionIds($this->get(route('transactions.index', $query)));

    expect($ids(['from' => '2026-09-01', 'to' => '2026-09-01']))->toBe([$lateOnFirst->id])
        ->and($ids(['to' => '2026-08-31']))->toBe([$lateOnAugust31->id])
        ->and($ids(['from' => '2026-09-02']))->toBe([$midnightOnSecond->id]);

    // The CSV uses the same local days.
    $csv = $this->get(route('transactions.export', ['from' => '2026-09-01', 'to' => '2026-09-01']))->streamedContent();
    expect(substr_count($csv, "\n"))->toBe(2)
        ->and($csv)->toContain('2026-09-01T23:30:00-06:00');

    // Another timezone moves the same instants to other days. In Tokyo
    // (UTC+9) they are 14:30 on the 2nd, 09:30 on the 1st, and 15:00 on the 2nd.
    $organization->update(['timezone' => 'Asia/Tokyo']);
    expect($ids(['from' => '2026-09-02', 'to' => '2026-09-02']))->toBe([$midnightOnSecond->id, $lateOnFirst->id])
        ->and($ids(['from' => '2026-09-01', 'to' => '2026-09-01']))->toBe([$lateOnAugust31->id]);
});

test('csv export shows times in the organization\'s timezone', function () {
    [$user, $organization] = cardOwner(['timezone' => 'America/Mexico_City']);
    $card = Card::factory()->forOrganization($organization)->create(['code' => 'YGFT-TZ01']);
    Transaction::factory()->for($card)->create([
        'type' => 'load',
        'performed_by' => $user->id,
        'created_at' => Carbon::parse('2026-09-02 05:30:00', 'UTC'),
    ]);

    $this->actingAs($user);
    $rows = fn () => array_map(
        fn (string $line) => str_getcsv($line, escape: ''),
        array_values(array_filter(explode("\n", $this->get(route('transactions.export'))->streamedContent()))),
    );

    expect($rows()[1][0])->toBe('2026-09-01T23:30:00-06:00');

    $organization->update(['timezone' => 'Asia/Tokyo']);
    expect($rows()[1][0])->toBe('2026-09-02T14:30:00+09:00');

    // The page still gets the instant; the browser formats it in the prop's timezone.
    $this->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentOrganization.timezone', 'Asia/Tokyo')
            ->where('transactions.data.0.created_at', '2026-09-02T05:30:00+00:00'));
});
