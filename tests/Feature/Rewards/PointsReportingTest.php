<?php

use App\Enums\EarnMode;
use App\Enums\MembershipRole;
use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member of a new business, with rewards on unless told otherwise.
 *
 * @return array{0: User, 1: Organization, 2: Program, 3: Card}
 */
function reportingSetup(bool $rewards = true): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, MembershipRole::Owner)->create();
    $program = Program::factory()->for($organization)->create();

    if ($rewards) {
        $program->update(['rewards_enabled' => true, 'earn_mode' => EarnMode::Visits]);
    }

    $card = Card::factory()->forOrganization($organization)->create(['points' => 0]);

    return [$user, $organization, $program, $card];
}

function pointsEntry(Card $card, TransactionType $type, int $points, int $after = 0): Transaction
{
    return Transaction::factory()->create([
        'card_id' => $card->id,
        'type' => $type,
        'unit' => TransactionUnit::Points,
        'amount' => number_format($points, 2, '.', ''),
        'balance_after' => number_format($after, 2, '.', ''),
    ]);
}

// --- The transactions page

test('the type filter offers the points types to a business that uses rewards', function () {
    [$user] = reportingSetup();

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('types', ['load', 'spend', 'adjustment', 'earn', 'redeem', 'expire', 'points_adjustment']));
});

test('a business that never used rewards keeps the money types only', function () {
    [$user] = reportingSetup(rewards: false);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page->where('types', ['load', 'spend', 'adjustment']));
});

test('a business that turned rewards off still sees its points history', function () {
    [$user, , $program, $card] = reportingSetup();
    pointsEntry($card, TransactionType::Earn, 5, 5);
    $program->update(['rewards_enabled' => false]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('types', ['load', 'spend', 'adjustment', 'earn', 'redeem', 'expire', 'points_adjustment'])
            ->has('transactions.data', 1));
});

test('the list filters by a points type, and the export follows', function (string $type) {
    [$user, , , $card] = reportingSetup();
    pointsEntry($card, TransactionType::Earn, 5, 5);
    pointsEntry($card, TransactionType::Redeem, 5, 0);
    pointsEntry($card, TransactionType::Expire, 2, 0);
    pointsEntry($card, TransactionType::PointsAdjustment, 1, 1);
    Transaction::factory()->create(['card_id' => $card->id]);

    $this->actingAs($user)->get(route('transactions.index', ['type' => $type]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.type', $type)
            ->where('transactions.data.0.unit', 'points')
            ->where('filters.type', $type));

    $csv = $this->actingAs($user)->get(route('transactions.export', ['type' => $type]))->streamedContent();

    expect(array_values(array_filter(explode("\n", $csv))))->toHaveCount(2);
})->with(['earn', 'redeem', 'expire', 'points_adjustment']);

test('without a filter the list holds money and points together', function () {
    [$user, , , $card] = reportingSetup();
    pointsEntry($card, TransactionType::Earn, 5, 5);
    Transaction::factory()->create(['card_id' => $card->id]);

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 2));
});

test('the export tells points from money by unit and writes points as whole numbers', function () {
    [$user, , , $card] = reportingSetup();
    pointsEntry($card, TransactionType::Earn, 12, 12);
    Transaction::factory()->create(['card_id' => $card->id, 'amount' => '25.50', 'balance_after' => '25.50']);

    $csv = $this->actingAs($user)->get(route('transactions.export'))->streamedContent();
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $csv))));

    expect($rows[0])->toBe(['Date', 'Card', 'Type', 'Unit', 'Amount', 'Balance after', 'Note', 'Performed by']);

    $byUnit = collect(array_slice($rows, 1))->keyBy(fn (array $row) => $row[3]);

    expect($byUnit['Points'][2])->toBe('Earned')
        ->and($byUnit['Points'][4])->toBe('12')
        ->and($byUnit['Points'][5])->toBe('12')
        ->and($byUnit['Money'][2])->toBe('Load')
        ->and($byUnit['Money'][4])->toBe('25.50');
});

// --- Analytics

test('analytics counts points earned, redeemed and expired, and what is outstanding', function () {
    [$user, $organization, , $card] = reportingSetup();
    $card->update(['points' => 7]);
    Card::factory()->forOrganization($organization)->create(['points' => 5]);
    // Another business's points are not ours.
    Card::factory()->create(['points' => 100]);

    pointsEntry($card, TransactionType::Earn, 6, 6);
    pointsEntry($card, TransactionType::Earn, 4, 10);
    pointsEntry($card, TransactionType::Redeem, 3, 7);
    pointsEntry($card, TransactionType::Expire, 2, 5);
    // An adjustment is a correction: it is in the history but not in these.
    pointsEntry($card, TransactionType::PointsAdjustment, 9, 9);
    // Money keeps its own numbers.
    Transaction::factory()->create(['card_id' => $card->id, 'amount' => '20.00']);

    $this->actingAs($user)->get(route('analytics'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pointsEnabled', true)
            ->where('summary.pointsEarned', 10)
            ->where('summary.pointsRedeemed', 3)
            ->where('summary.pointsExpired', 2)
            ->where('summary.pointsOutstanding', 12)
            ->where('summary.transactions', 1)
            ->where('summary.loadAmount', '20.00')
            ->where('series.29.pointsEarned', 10)
            ->where('series.29.pointsRedeemed', 3)
            ->where('series.29.pointsExpired', 2)
            ->where('series.28.pointsEarned', 0));
});

test('analytics leaves the points out for a business that never used rewards', function () {
    [$user] = reportingSetup(rewards: false);

    $this->actingAs($user)->get(route('analytics'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('pointsEnabled', false)
            ->where('summary.pointsOutstanding', 0));
});

test('analytics only counts points inside the chosen range', function () {
    [$user, , , $card] = reportingSetup();
    $old = pointsEntry($card, TransactionType::Earn, 8, 8);
    $old->forceFill(['created_at' => now()->subDays(20)])->save();
    pointsEntry($card, TransactionType::Earn, 1, 9);

    $this->actingAs($user)->get(route('analytics', ['days' => 7]))
        ->assertInertia(fn (Assert $page) => $page->where('summary.pointsEarned', 1));

    $this->actingAs($user)->get(route('analytics', ['days' => 30]))
        ->assertInertia(fn (Assert $page) => $page->where('summary.pointsEarned', 9));
});
