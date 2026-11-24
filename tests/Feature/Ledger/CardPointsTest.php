<?php

use App\Enums\CardStatus;
use App\Enums\EarnMode;
use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use App\Exceptions\LedgerException;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use Illuminate\Support\Carbon;

/**
 * A card with the given points in an organization whose program has rewards
 * on, and its owner.
 *
 * @param  array<string, mixed>  $card
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Card, 2: Organization}
 */
function pointsCard(int $points = 0, array $card = [], bool $rewards = true, array $organization = []): array
{
    [$user, $organization, $program] = cardOwner($organization);

    if ($rewards) {
        $program->update(['rewards_enabled' => true, 'earn_mode' => EarnMode::Visits]);
    }

    $card = Card::factory()->forOrganization($organization)->create(['points' => $points, ...$card]);

    return [$user, $card, $organization];
}

function pointsLedger(): CardLedger
{
    return app(CardLedger::class);
}

function pointsRefusal(Closure $call): LedgerException
{
    try {
        $call();
    } catch (LedgerException $exception) {
        return $exception;
    }

    throw new RuntimeException('The ledger accepted a change it should have refused.');
}

test('earn adds points, records a points entry and marks a use', function () {
    [$user, $card] = pointsCard(10, ['balance' => '25.00']);

    Carbon::setTestNow('2026-11-24 09:00:00');
    $transaction = pointsLedger()->earn($card, 5, $user, 'Morning coffee');

    expect($card->points)->toBe(15)
        ->and($card->fresh()->points)->toBe(15)
        ->and($transaction->type)->toBe(TransactionType::Earn)
        ->and($transaction->unit)->toBe(TransactionUnit::Points)
        ->and($transaction->amount)->toBe('5.00')
        ->and($transaction->balance_after)->toBe('15.00')
        ->and($transaction->note)->toBe('Morning coffee')
        ->and($transaction->performed_by)->toBe($user->id)
        ->and($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-11-24 09:00:00');

    // Money and status are untouched.
    expect($card->fresh()->balance)->toBe('25.00')
        ->and($card->fresh()->status)->toBe(CardStatus::Active);

    Carbon::setTestNow();
});

test('money entries are money entries', function () {
    [$user, $card] = ledgerCard('10.00');

    $transaction = ledger()->load($card, '5.00', $user);

    expect($transaction->fresh()->unit)->toBe(TransactionUnit::Money)
        ->and($card->fresh()->points)->toBe(0);
});

test('redeem takes points and refuses more than the card has', function () {
    [$user, $card] = pointsCard(10);

    $transaction = pointsLedger()->redeem($card, 4, $user);

    expect($card->fresh()->points)->toBe(6)
        ->and($transaction->type)->toBe(TransactionType::Redeem)
        ->and($transaction->unit)->toBe(TransactionUnit::Points)
        ->and($transaction->balance_after)->toBe('6.00');

    $refusal = pointsRefusal(fn () => pointsLedger()->redeem($card, 7, $user));

    expect($refusal->getMessage())->toBe('Not enough points.')
        ->and($refusal->field)->toBe('points')
        ->and($card->fresh()->points)->toBe(6)
        ->and(Transaction::count())->toBe(1);

    // Exactly the balance is allowed.
    pointsLedger()->redeem($card, 6, $user);
    expect($card->fresh()->points)->toBe(0);
});

test('redeeming every point does not deplete the card', function () {
    [$user, $card] = pointsCard(3, ['balance' => '0.00']);

    pointsLedger()->redeem($card, 3, $user);

    expect($card->fresh()->status)->toBe(CardStatus::Active);
});

test('a card that never held money stays active through earn and redeem', function () {
    [$user, $card] = pointsCard();

    pointsLedger()->earn($card, 10, $user);
    pointsLedger()->redeem($card, 10, $user);

    expect($card->fresh()->status)->toBe(CardStatus::Active)
        ->and($card->fresh()->balance)->toBe('0.00');
});

test('adjustPoints corrects up or down, needs a note and is not a use', function () {
    [$user, $card] = pointsCard(10);

    $up = pointsLedger()->adjustPoints($card, 5, $user, 'Missed visit');
    $down = pointsLedger()->adjustPoints($card, -12, $user, 'Duplicate');

    expect($up->type)->toBe(TransactionType::PointsAdjustment)
        ->and($up->unit)->toBe(TransactionUnit::Points)
        ->and($up->amount)->toBe('5.00')
        ->and($down->amount)->toBe('-12.00')
        ->and($down->balance_after)->toBe('3.00')
        ->and($card->fresh()->points)->toBe(3)
        ->and($card->fresh()->last_used_at)->toBeNull();

    $noNote = pointsRefusal(fn () => pointsLedger()->adjustPoints($card, 1, $user, ' '));
    $zero = pointsRefusal(fn () => pointsLedger()->adjustPoints($card, 0, $user, 'Nothing'));
    $below = pointsRefusal(fn () => pointsLedger()->adjustPoints($card, -4, $user, 'Too many'));

    expect($noNote->field)->toBe('note')
        ->and($zero->getMessage())->toBe('The points adjustment cannot be 0.')
        ->and($below->getMessage())->toBe('The adjustment would leave negative points.')
        ->and($card->fresh()->points)->toBe(3)
        ->and(Transaction::count())->toBe(2);
});

test('earn and redeem need a whole number above zero', function (int $points) {
    [$user, $card] = pointsCard(5);

    expect(fn () => pointsLedger()->earn($card, $points, $user))->toThrow(LedgerException::class)
        ->and(fn () => pointsLedger()->redeem($card, $points, $user))->toThrow(LedgerException::class)
        ->and($card->fresh()->points)->toBe(5)
        ->and(Transaction::count())->toBe(0);
})->with(['zero' => 0, 'negative' => -3]);

test('points cannot go above the maximum', function () {
    [$user, $card] = pointsCard(CardLedger::MAX_POINTS - 1);

    $refusal = pointsRefusal(fn () => pointsLedger()->earn($card, 2, $user));

    expect($refusal->field)->toBe('points')
        ->and($card->fresh()->points)->toBe(CardLedger::MAX_POINTS - 1);

    pointsLedger()->earn($card, 1, $user);
    expect($card->fresh()->points)->toBe(CardLedger::MAX_POINTS);
});

test('points are refused while rewards are off for the program', function () {
    [$user, $card] = pointsCard(5, rewards: false);

    foreach ([
        fn () => pointsLedger()->earn($card, 1, $user),
        fn () => pointsLedger()->redeem($card, 1, $user),
        fn () => pointsLedger()->adjustPoints($card, 1, $user, 'Correction'),
    ] as $call) {
        $refusal = pointsRefusal($call);

        expect($refusal->getMessage())->toBe('Rewards are not turned on for this business.')
            ->and($refusal->field)->toBe('rewards');
    }

    expect($card->fresh()->points)->toBe(5)
        ->and(Transaction::count())->toBe(0);
});

test('points entries get the same refusals as money', function (CardStatus $status, string $message, string $field) {
    [$user, $card] = pointsCard(5, ['status' => $status]);

    $refusal = pointsRefusal(fn () => pointsLedger()->earn($card, 1, $user));

    expect($refusal->getMessage())->toBe($message)
        ->and($refusal->field)->toBe($field)
        ->and($card->fresh()->points)->toBe(5)
        ->and(Transaction::count())->toBe(0);
})->with([
    'frozen' => [CardStatus::Frozen, 'This card is frozen.', 'card'],
    'cancelled' => [CardStatus::Cancelled, 'This card is cancelled.', 'card'],
    'inactive' => [CardStatus::Inactive, 'This card is not activated yet.', 'card'],
]);

test('points entries are refused for a suspended organization', function () {
    [$user, $card, $organization] = pointsCard(5);
    Organization::query()->whereKey($organization->id)->update(['status' => 'suspended']);

    $refusal = pointsRefusal(fn () => pointsLedger()->earn($card, 1, $user));

    expect($refusal->field)->toBe('organization')
        ->and($card->fresh()->points)->toBe(5);
});

test('a depleted card still takes points', function () {
    [$user, $card] = pointsCard(0, ['status' => CardStatus::Depleted]);

    pointsLedger()->earn($card, 2, $user);

    expect($card->fresh()->points)->toBe(2)
        ->and($card->fresh()->status)->toBe(CardStatus::Depleted);
});

test('activateWithoutFunds activates an inactive card with no ledger entry', function () {
    [$user, $card] = ledgerCard('0.00', ['status' => CardStatus::Inactive]);

    Carbon::setTestNow('2026-11-24 10:00:00');
    ledger()->activateWithoutFunds($card);

    $fresh = $card->fresh();
    expect($card->status)->toBe(CardStatus::Active)
        ->and($fresh->status)->toBe(CardStatus::Active)
        ->and($fresh->balance)->toBe('0.00')
        ->and($fresh->activated_at->toDateTimeString())->toBe('2026-11-24 10:00:00')
        ->and($fresh->last_used_at)->toBeNull()
        ->and(Transaction::count())->toBe(0);

    // It works like any other active card, and a zero-money card is not depleted.
    ledger()->load($card, '5.00', $user);
    expect($card->fresh()->balance)->toBe('5.00');

    Carbon::setTestNow();
});

test('activateWithoutFunds refuses a card that is not inactive', function (CardStatus $status, string $message) {
    [, $card] = ledgerCard('10.00', ['status' => $status]);

    $refusal = ledgerRefusal(fn () => ledger()->activateWithoutFunds($card));

    expect($refusal->getMessage())->toBe($message)
        ->and($refusal->field)->toBe('card')
        ->and($card->fresh()->status)->toBe($status)
        ->and($card->fresh()->activated_at)->toBeNull();
})->with([
    'active' => [CardStatus::Active, 'This card is already activated.'],
    'frozen' => [CardStatus::Frozen, 'This card is frozen.'],
    'cancelled' => [CardStatus::Cancelled, 'This card is cancelled.'],
]);

test('activateWithoutFunds is refused at the card limit and for a suspended organization', function () {
    [, $card, $organization] = ledgerCard('0.00', ['status' => CardStatus::Inactive], ['card_limit' => 1]);
    Card::factory()->forOrganization($organization)->create();

    $atLimit = ledgerRefusal(fn () => ledger()->activateWithoutFunds($card));

    expect($atLimit->field)->toBe('card_limit')
        ->and($card->fresh()->status)->toBe(CardStatus::Inactive);

    Organization::query()->whereKey($organization->id)->update(['status' => 'suspended', 'card_limit' => 10]);

    $suspended = ledgerRefusal(fn () => ledger()->activateWithoutFunds($card));

    expect($suspended->field)->toBe('organization')
        ->and($card->fresh()->status)->toBe(CardStatus::Inactive);
});

test('programs start with rewards off and keep their rewards config', function () {
    $program = Program::factory()->create();

    expect($program->fresh()->rewards_enabled)->toBeFalse()
        ->and($program->fresh()->earn_mode)->toBeNull()
        ->and($program->fresh()->earn_rate)->toBeNull()
        ->and($program->fresh()->points_expiry_months)->toBeNull();

    $program->update(['rewards_enabled' => true, 'earn_mode' => EarnMode::Spend, 'earn_rate' => '1.50', 'points_expiry_months' => 12]);

    expect($program->fresh()->rewards_enabled)->toBeTrue()
        ->and($program->fresh()->earn_mode)->toBe(EarnMode::Spend)
        ->and($program->fresh()->earn_rate)->toBe('1.50')
        ->and($program->fresh()->points_expiry_months)->toBe(12);
});
