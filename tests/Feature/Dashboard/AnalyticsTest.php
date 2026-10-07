<?php

use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardBatchIssuer;
use App\Services\CardLedger;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @param  array<string, mixed>  $attributes
 */
function analyticsTransaction(Card $card, User $user, string $type, string $amount, Carbon $at, array $attributes = []): Transaction
{
    return Transaction::factory()->for($card)->create([
        'type' => $type,
        'amount' => $amount,
        'balance_after' => '100.00',
        'note' => $type === 'adjustment' ? 'Fix' : null,
        'performed_by' => $user->id,
        'created_at' => $at,
        ...$attributes,
    ]);
}

/**
 * The series row for a local date.
 *
 * @param  array<string, mixed>  $props
 * @return array<string, mixed>
 */
function analyticsDay(array $props, string $date): array
{
    return collect($props['series'])->firstWhere('date', $date);
}

test('analytics only counts the current organization', function () {
    // 2026-09-20 12:00 in Mexico City.
    Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00', 'UTC'));

    [$user, $organization] = cardOwner();
    [$otherUser, $other] = cardOwner();

    $local = fn (Organization $org, string $at) => Carbon::parse($at, $org->timezone)->utc();

    $mine = Card::factory()->forOrganization($organization)->create(['created_at' => $local($organization, '2026-09-18 10:00:00')]);
    Card::factory()->forOrganization($organization)->create(['created_at' => $local($organization, '2026-09-19 10:00:00')]);
    analyticsTransaction($mine, $user, 'load', '100.00', $local($organization, '2026-09-18 10:05:00'));
    analyticsTransaction($mine, $user, 'spend', '30.50', $local($organization, '2026-09-19 13:00:00'));
    analyticsTransaction($mine, $user, 'adjustment', '-4.00', $local($organization, '2026-09-19 14:00:00'));

    // The other organization is busier and must not leak into the numbers.
    $theirs = Card::factory()->count(5)->forOrganization($other)->create(['created_at' => $local($other, '2026-09-19 09:00:00')]);
    foreach ($theirs as $card) {
        analyticsTransaction($card, $otherUser, 'load', '500.00', $local($other, '2026-09-19 09:30:00'));
        analyticsTransaction($card, $otherUser, 'spend', '50.00', $local($other, '2026-09-19 09:45:00'));
    }

    $response = $this->actingAs($user)
        ->get(route('analytics', ['days' => 7]))
        ->assertOk();

    $response->assertInertia(fn (Assert $page) => $page
        ->component('analytics/index')
        ->where('days', 7)
        ->where('from', '2026-09-14')
        ->where('to', '2026-09-20')
        ->has('series', 7)
        ->where('summary.cards', 2)
        ->where('summary.transactions', 3)
        ->where('summary.loadAmount', '100.00')
        ->where('summary.spendAmount', '30.50')
        ->where('currency', 'MXN'));

    $props = $response->viewData('page')['props'];

    expect(analyticsDay($props, '2026-09-18'))->toMatchArray(['cards' => 1, 'load' => 1, 'spend' => 0, 'loadAmount' => '100.00'])
        ->and(analyticsDay($props, '2026-09-19'))->toMatchArray(['cards' => 1, 'load' => 0, 'spend' => 1, 'adjustment' => 1, 'spendAmount' => '30.50'])
        ->and(array_sum(array_column($props['series'], 'cards')))->toBe(2);

    // And the other organization sees only its own.
    $this->actingAs($otherUser)
        ->get(route('analytics', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.cards', 5)
            ->where('summary.transactions', 10)
            ->where('summary.loadAmount', '2500.00'));
});

test('analytics groups by day in the organization timezone', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00', 'UTC'));

    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create([
        // 23:30 local on the 15th is 05:30 UTC on the 16th.
        'created_at' => Carbon::parse('2026-09-15 23:30:00', $organization->timezone)->utc(),
    ]);

    analyticsTransaction($card, $user, 'load', '10.00', Carbon::parse('2026-09-15 23:30:00', $organization->timezone)->utc());
    analyticsTransaction($card, $user, 'load', '20.00', Carbon::parse('2026-09-16 00:00:00', $organization->timezone)->utc());
    // Before the 7-day range: 23:59 local on the 13th.
    analyticsTransaction($card, $user, 'load', '40.00', Carbon::parse('2026-09-13 23:59:00', $organization->timezone)->utc());

    $props = $this->actingAs($user)
        ->get(route('analytics', ['days' => 7]))
        ->viewData('page')['props'];

    expect(analyticsDay($props, '2026-09-15'))->toMatchArray(['cards' => 1, 'load' => 1, 'loadAmount' => '10.00'])
        ->and(analyticsDay($props, '2026-09-16'))->toMatchArray(['cards' => 0, 'load' => 1, 'loadAmount' => '20.00'])
        ->and($props['summary']['transactions'])->toBe(2)
        ->and(array_column($props['series'], 'date'))->toBe([
            '2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18', '2026-09-19', '2026-09-20',
        ]);
});

test('analytics range falls back to 30 days', function (mixed $days) {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->get(route('analytics', ['days' => $days]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('days', 30)
            ->has('series', 30)
            ->where('ranges', [7, 30, 60, 90]));
})->with(['', '5', '365', 'abc']);

test('analytics is readable by a suspended organization and needs login', function () {
    [$user] = cardOwner(['status' => 'suspended']);

    $this->actingAs($user)->get(route('analytics'))->assertOk();

    auth()->logout();

    $this->get(route('analytics'))->assertRedirect(route('login'));
});

test('analytics counts activation rather than inventory creation and shows current balance', function () {
    Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00', 'UTC'));
    [$user, $organization] = cardOwner();
    Card::factory()->forOrganization($organization)->create(['balance' => '12.50', 'created_at' => now()->subDays(40)]);
    $batch = app(CardBatchIssuer::class)->issue($organization, 3, $user, byAdmin: false);
    $batch->cards()->update(['created_at' => now()->subDays(40)]);
    app(CardLedger::class)->activate($batch->cards()->first(), '25.00', $user);
    Card::factory()->create(['balance' => '999.00']);

    $this->actingAs($user)->get(route('analytics', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('summary.cards', 1)
            ->where('currentBalance', '37.50')
            ->where('series.6.cards', 1));
});
