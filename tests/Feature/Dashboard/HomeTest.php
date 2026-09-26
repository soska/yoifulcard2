<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

test('home totals match the database', function () {
    // 2026-09-15 12:00 in Mexico City.
    Carbon::setTestNow(Carbon::parse('2026-09-15 18:00:00', 'UTC'));

    [$user, $organization] = cardOwner(['card_limit' => 10]);

    $cards = collect([
        ['balance' => '120.50', 'status' => CardStatus::Active],
        ['balance' => '0.00', 'status' => CardStatus::Depleted],
        ['balance' => '33.25', 'status' => CardStatus::Frozen],
        ['balance' => '10.00', 'status' => CardStatus::Active],
    ])->map(fn (array $attributes) => Card::factory()->forOrganization($organization)->create($attributes));

    // Another organization's card and transactions never count.
    [, $other] = cardOwner();
    $otherCard = Card::factory()->forOrganization($other)->create(['balance' => '999.99']);
    Transaction::factory()->for($otherCard)->create(['created_at' => now()]);

    // The month starts at local midnight on the 1st (06:00 UTC). 23:30 local
    // on August 31 is 05:30 UTC on September 1: a UTC month would count it,
    // the organization's month must not.
    $inMonth = [
        Carbon::parse('2026-09-01 00:30:00', $organization->timezone)->utc(),
        Carbon::parse('2026-09-10 12:00:00', $organization->timezone)->utc(),
        Carbon::parse('2026-09-15 11:00:00', $organization->timezone)->utc(),
    ];
    $beforeMonth = Carbon::parse('2026-08-31 23:30:00', $organization->timezone)->utc();

    foreach ($inMonth as $index => $at) {
        Transaction::factory()->for($cards[$index % 4])->create(['created_at' => $at, 'performed_by' => $user->id]);
    }
    Transaction::factory()->for($cards[0])->create(['created_at' => $beforeMonth, 'performed_by' => $user->id]);

    $expectedBalance = Card::query()->forOrganization($organization)->pluck('balance')
        ->reduce(fn (string $sum, string $balance) => bcadd($sum, $balance, 2), '0.00');

    expect($expectedBalance)->toBe('163.75');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('stats.cards', Card::query()->forOrganization($organization)->count())
            ->where('stats.cards', 4)
            ->where('stats.active', 2)
            ->where('stats.frozen', 1)
            ->where('stats.depleted', 1)
            ->where('stats.cancelled', 0)
            ->where('stats.outstandingBalance', $expectedBalance)
            ->where('stats.month', '2026-09-01')
            ->where('stats.monthTransactions', 3)
            ->where('currency', 'MXN')
            ->where('usage.used', 4)
            ->where('usage.limit', 10)
            ->where('canCreateCards', true)
            ->has('recentTransactions', Transaction::query()->forOrganization($organization)->count())
            ->where('recentTransactions.0.created_at', $inMonth[2]->toIso8601String()));
});

test('home lists at most ten recent transactions, newest first', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    foreach (range(1, 12) as $minute) {
        Transaction::factory()->for($card)->create(['created_at' => now()->subMinutes($minute)]);
    }

    $newest = Transaction::query()->latest()->first();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions', 10)
            ->where('recentTransactions.0.id', $newest->id)
            ->missing('recentTransactions.0.card.qr_token'));
});

test('home with no cards shows zero totals', function () {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.cards', 0)
            ->where('stats.outstandingBalance', '0.00')
            ->where('stats.monthTransactions', 0)
            ->has('recentTransactions', 0));
});

test('suspended organization home cannot create cards', function () {
    [$user] = cardOwner(['status' => 'suspended']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canCreateCards', false));
});

test('dashboard shows the organization empty state without a membership', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->component('organizations/empty'));

    expect(Organization::query()->count())->toBe(0);
});
