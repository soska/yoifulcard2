<?php

use App\Enums\CardStatus;
use App\Enums\TransactionType;
use App\Exceptions\LedgerException;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A card with the given balance in a new organization, and its owner.
 *
 * @param  array<string, mixed>  $card
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Card, 2: Organization}
 */
function ledgerCard(string $balance = '0.00', array $card = [], array $organization = []): array
{
    [$user, $organization] = cardOwner($organization);

    $card = Card::factory()->forOrganization($organization)->create(['balance' => $balance, ...$card]);

    return [$user, $card, $organization];
}

function ledger(): CardLedger
{
    return app(CardLedger::class);
}

/**
 * Run a ledger call that must fail, and return the exception.
 */
function ledgerRefusal(Closure $call): LedgerException
{
    try {
        $call();
    } catch (LedgerException $exception) {
        return $exception;
    }

    throw new RuntimeException('The ledger accepted a change it should have refused.');
}

test('load increases balance and records balance_after', function () {
    [$user, $card] = ledgerCard('10.00');

    $transaction = ledger()->load($card, '25.50', $user, 'Birthday gift');

    expect($card->balance)->toBe('35.50')
        ->and($card->fresh()->balance)->toBe('35.50')
        ->and($transaction->type)->toBe(TransactionType::Load)
        ->and($transaction->amount)->toBe('25.50')
        ->and($transaction->balance_after)->toBe('35.50')
        ->and($transaction->note)->toBe('Birthday gift')
        ->and($transaction->performed_by)->toBe($user->id)
        ->and($transaction->card_id)->toBe($card->id)
        ->and($transaction->created_at)->not->toBeNull();

    $row = Transaction::sole();
    expect($row->balance_after)->toBe('35.50')
        ->and($row->amount)->toBe('25.50');
});

test('spend decreases balance and records balance_after', function () {
    [$user, $card] = ledgerCard('40.00');

    $transaction = ledger()->spend($card, '15.25', $user);

    expect($card->fresh()->balance)->toBe('24.75')
        ->and($transaction->type)->toBe(TransactionType::Spend)
        ->and($transaction->amount)->toBe('15.25')
        ->and($transaction->balance_after)->toBe('24.75')
        ->and($transaction->note)->toBeNull()
        ->and(Transaction::sole()->balance_after)->toBe('24.75');
});

test('spending the whole balance depletes the card and a load reactivates it', function () {
    [$user, $card] = ledgerCard('10.00');

    ledger()->spend($card, '10', $user);
    expect($card->fresh()->status)->toBe(CardStatus::Depleted)
        ->and($card->fresh()->balance)->toBe('0.00');

    ledger()->load($card, '5', $user);
    expect($card->fresh()->status)->toBe(CardStatus::Active)
        ->and($card->fresh()->balance)->toBe('5.00');
});

test('adjustment moves balance both ways and requires a note', function () {
    [$user, $card] = ledgerCard('20.00');

    $up = ledger()->adjust($card, '5.55', $user, 'Missed load last week');
    expect($card->fresh()->balance)->toBe('25.55')
        ->and($up->type)->toBe(TransactionType::Adjustment)
        ->and($up->amount)->toBe('5.55')
        ->and($up->balance_after)->toBe('25.55')
        ->and($up->note)->toBe('Missed load last week');

    $down = ledger()->adjust($card, '-10.05', $user, 'Double charge refunded in cash');
    expect($card->fresh()->balance)->toBe('15.50')
        ->and($down->amount)->toBe('-10.05')
        ->and($down->balance_after)->toBe('15.50');

    foreach ([null, '', '   '] as $note) {
        $refusal = ledgerRefusal(fn () => ledger()->adjust($card, '1.00', $user, $note));

        expect($refusal->field)->toBe('note')
            ->and($refusal->getMessage())->toBe('A note is required for adjustments.');
    }

    expect(ledgerRefusal(fn () => ledger()->adjust($card, '0.00', $user, 'Nothing'))->getMessage())
        ->toBe('The adjustment cannot be 0.');

    expect($card->fresh()->balance)->toBe('15.50')
        ->and(Transaction::count())->toBe(2);
});

test('adjustment below zero is rejected', function () {
    [$user, $card] = ledgerCard('5.00');

    $refusal = ledgerRefusal(fn () => ledger()->adjust($card, '-5.01', $user, 'Too much'));

    expect($refusal->getMessage())->toBe('The adjustment would leave a negative balance.')
        ->and($card->fresh()->balance)->toBe('5.00')
        ->and(Transaction::count())->toBe(0);

    // Down to exactly zero is allowed.
    ledger()->adjust($card, '-5.00', $user, 'Card returned');
    expect($card->fresh()->balance)->toBe('0.00');
});

test('spend above balance throws and leaves card and transactions unchanged', function () {
    [$user, $card] = ledgerCard('10.00');
    $before = $card->fresh();

    $refusal = ledgerRefusal(fn () => ledger()->spend($card, '10.01', $user));

    $after = $card->fresh();
    expect($refusal->getMessage())->toBe('Insufficient balance.')
        ->and($refusal->field)->toBe('amount')
        ->and($after->balance)->toBe('10.00')
        ->and($after->status)->toBe(CardStatus::Active)
        ->and($after->last_used_at)->toBeNull()
        ->and($after->updated_at->equalTo($before->updated_at))->toBeTrue()
        ->and(Transaction::count())->toBe(0);
});

test('amounts must be positive decimal strings with at most two decimals', function (string $amount) {
    [$user, $card] = ledgerCard('10.00');

    expect(fn () => ledger()->load($card, $amount, $user))->toThrow(LedgerException::class)
        ->and(fn () => ledger()->spend($card, $amount, $user))->toThrow(LedgerException::class)
        ->and($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
})->with(['zero' => '0', 'negative' => '-1.00', 'three decimals' => '1.005', 'exponent' => '1e2', 'empty' => '', 'text' => 'ten']);

test('loads cannot push the balance past the column maximum', function () {
    [$user, $card] = ledgerCard('99999999.00');

    expect(ledgerRefusal(fn () => ledger()->load($card, '1.00', $user))->getMessage())
        ->toBe('The balance cannot be more than 99999999.99.');

    ledger()->load($card, '0.99', $user);
    expect($card->fresh()->balance)->toBe('99999999.99');
});

test('frozen card rejects load and spend', function () {
    [$user, $card] = ledgerCard('10.00', ['status' => CardStatus::Frozen]);

    foreach (['load', 'spend'] as $method) {
        $refusal = ledgerRefusal(fn () => ledger()->{$method}($card, '1.00', $user));

        expect($refusal->getMessage())->toBe('This card is frozen.')
            ->and($refusal->field)->toBe('card');
    }

    expect($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
});

test('cancelled card rejects load and spend', function () {
    [$user, $card] = ledgerCard('10.00', ['status' => CardStatus::Cancelled]);

    foreach (['load', 'spend'] as $method) {
        expect(ledgerRefusal(fn () => ledger()->{$method}($card, '1.00', $user))->getMessage())
            ->toBe('This card is cancelled.');
    }

    expect($card->fresh()->balance)->toBe('10.00')
        ->and($card->fresh()->status)->toBe(CardStatus::Cancelled)
        ->and(Transaction::count())->toBe(0);
});

test('suspended organization rejects load, spend, and adjust in the service', function (string $status) {
    [$user, $card, $organization] = ledgerCard('10.00');

    // The model the caller holds still says active: the service must reread it.
    Organization::query()->whereKey($organization->id)->update(['status' => $status]);

    $calls = [
        'load' => fn () => ledger()->load($card, '1.00', $user),
        'spend' => fn () => ledger()->spend($card, '1.00', $user),
        'adjust' => fn () => ledger()->adjust($card, '1.00', $user, 'Correction'),
    ];

    foreach ($calls as $call) {
        $refusal = ledgerRefusal($call);

        expect($refusal->getMessage())->toBe('This business is suspended. Contact support.')
            ->and($refusal->field)->toBe('organization');
    }

    expect($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
})->with(['suspended', 'cancelled']);

test('amounts are never floats', function () {
    [$user, $card] = ledgerCard('0.00');

    ledger()->load($card, '0.10', $user);
    $transaction = ledger()->load($card, '0.20', $user);

    expect($card->balance)->toBeString()->toBe('0.30')
        ->and($transaction->balance_after)->toBeString()->toBe('0.30')
        ->and($card->fresh()->balance)->toBe('0.30')
        ->and(bccomp($card->fresh()->balance, '0.30', 2))->toBe(0);

    // In floats, 0.10 + 0.20 is 0.30000000000000004. The ledger refuses it
    // instead of rounding it.
    expect(fn () => ledger()->load($card, json_encode(0.1 + 0.2), $user))->toThrow(LedgerException::class);

    // Many small steps add up exactly.
    for ($i = 0; $i < 10; $i++) {
        ledger()->spend($card, '0.03', $user);
    }

    expect($card->fresh()->balance)->toBe('0.00')
        ->and(Transaction::query()->latest('created_at')->orderByDesc('id')->first()->balance_after)->toBeString();

    $raw = DB::table('cards')->where('id', $card->id)->value('balance');
    expect($raw)->toBeString()->toBe('0.00');
});

test('issue records a load without marking the card used', function () {
    [$user, $card] = ledgerCard();

    Carbon::setTestNow('2026-09-01 10:00:00');
    $transaction = ledger()->issue($card, '500', $user);

    expect($transaction->type)->toBe(TransactionType::Load)
        ->and($transaction->balance_after)->toBe('500.00')
        ->and($card->fresh()->balance)->toBe('500.00')
        ->and($card->fresh()->last_used_at)->toBeNull();

    // The first real use sets it.
    Carbon::setTestNow('2026-09-02 11:00:00');
    ledger()->spend($card, '85.50', $user);
    expect($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-09-02 11:00:00');

    Carbon::setTestNow();
});

test('last_used_at changes on load and spend but not on adjust', function () {
    [$user, $card] = ledgerCard('10.00');

    Carbon::setTestNow('2026-09-01 10:00:00');
    ledger()->adjust($card, '5.00', $user, 'Opening correction');
    expect($card->fresh()->last_used_at)->toBeNull();

    Carbon::setTestNow('2026-09-02 11:00:00');
    ledger()->load($card, '5.00', $user);
    expect($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-09-02 11:00:00');

    Carbon::setTestNow('2026-09-03 12:00:00');
    ledger()->spend($card, '5.00', $user);
    expect($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-09-03 12:00:00');

    Carbon::setTestNow('2026-09-04 13:00:00');
    ledger()->adjust($card, '-1.00', $user, 'Later correction');
    expect($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-09-03 12:00:00');

    Carbon::setTestNow();
});

test('activate moves an inactive card to active with a load that is not a use', function () {
    [$user, $card] = ledgerCard('0.00', ['status' => CardStatus::Inactive]);

    Carbon::setTestNow('2026-10-06 10:00:00');
    $transaction = ledger()->activate($card, '250', $user);

    $fresh = $card->fresh();
    expect($transaction->type)->toBe(TransactionType::Load)
        ->and($transaction->amount)->toBe('250.00')
        ->and($transaction->balance_after)->toBe('250.00')
        ->and($transaction->note)->toBeNull()
        ->and($transaction->performed_by)->toBe($user->id)
        ->and($card->status)->toBe(CardStatus::Active)
        ->and($fresh->status)->toBe(CardStatus::Active)
        ->and($fresh->balance)->toBe('250.00')
        ->and($fresh->activated_at->toDateTimeString())->toBe('2026-10-06 10:00:00')
        ->and($fresh->last_used_at)->toBeNull();

    // Once active, it works like any other card.
    ledger()->spend($card, '50', $user);
    expect($card->fresh()->balance)->toBe('200.00')
        ->and(Transaction::count())->toBe(2);

    Carbon::setTestNow();
});

test('activate requires a positive amount', function (string $amount) {
    [$user, $card] = ledgerCard('0.00', ['status' => CardStatus::Inactive]);

    expect(fn () => ledger()->activate($card, $amount, $user))->toThrow(LedgerException::class)
        ->and($card->fresh()->status)->toBe(CardStatus::Inactive)
        ->and($card->fresh()->activated_at)->toBeNull()
        ->and(Transaction::count())->toBe(0);
})->with(['zero' => '0', 'negative' => '-5.00', 'three decimals' => '1.005', 'empty' => '']);

test('activate refuses a card that is not inactive', function (CardStatus $status, string $message) {
    [$user, $card] = ledgerCard('10.00', ['status' => $status]);

    $refusal = ledgerRefusal(fn () => ledger()->activate($card, '20.00', $user));

    expect($refusal->getMessage())->toBe($message)
        ->and($refusal->field)->toBe('card')
        ->and($card->fresh()->status)->toBe($status)
        ->and($card->fresh()->balance)->toBe('10.00')
        ->and($card->fresh()->activated_at)->toBeNull()
        ->and(Transaction::count())->toBe(0);
})->with([
    'active' => [CardStatus::Active, 'This card is already activated.'],
    'depleted' => [CardStatus::Depleted, 'This card is already activated.'],
    'frozen' => [CardStatus::Frozen, 'This card is frozen.'],
    'cancelled' => [CardStatus::Cancelled, 'This card is cancelled.'],
]);

test('an inactive card refuses load, spend, and adjust', function () {
    [$user, $card] = ledgerCard('0.00', ['status' => CardStatus::Inactive]);

    $calls = [
        'load' => fn () => ledger()->load($card, '1.00', $user),
        'spend' => fn () => ledger()->spend($card, '1.00', $user),
        'adjust' => fn () => ledger()->adjust($card, '1.00', $user, 'Correction'),
        'issue' => fn () => ledger()->issue($card, '1.00', $user),
    ];

    foreach ($calls as $call) {
        $refusal = ledgerRefusal($call);

        expect($refusal->getMessage())->toBe('This card is not activated yet.')
            ->and($refusal->field)->toBe('card');
    }

    expect($card->fresh()->status)->toBe(CardStatus::Inactive)
        ->and($card->fresh()->balance)->toBe('0.00')
        ->and(Transaction::count())->toBe(0);
});

test('activate is refused at the card limit, and inactive cards do not count toward it', function () {
    [$user, $card, $organization] = ledgerCard('0.00', ['status' => CardStatus::Inactive], ['card_limit' => 2]);
    Card::factory()->forOrganization($organization)->inactive()->count(3)->create();
    $active = Card::factory()->forOrganization($organization)->create();

    expect($organization->cardUsage())->toMatchArray(['used' => 1, 'stock' => 4, 'atLimit' => false]);

    // The last slot.
    ledger()->activate($card, '10.00', $user);
    expect($organization->cardUsage())->toMatchArray(['used' => 2, 'stock' => 3, 'atLimit' => true]);

    $next = Card::query()->forOrganization($organization)->where('status', CardStatus::Inactive)->firstOrFail();
    $refusal = ledgerRefusal(fn () => ledger()->activate($next, '10.00', $user));

    expect($refusal->getMessage())->toBe('You have reached your plan limit of 2 cards. Contact support to raise the limit.')
        ->and($refusal->field)->toBe('card_limit')
        ->and($next->fresh()->status)->toBe(CardStatus::Inactive)
        ->and(Transaction::count())->toBe(1);

    // A cancelled card still takes its slot, like before.
    $active->update(['status' => CardStatus::Cancelled]);
    expect($organization->cardUsage()['used'])->toBe(2);
});

test('activate is refused for a suspended organization', function () {
    [$user, $card, $organization] = ledgerCard('0.00', ['status' => CardStatus::Inactive]);
    Organization::query()->whereKey($organization->id)->update(['status' => 'suspended']);

    $refusal = ledgerRefusal(fn () => ledger()->activate($card, '10.00', $user));

    expect($refusal->field)->toBe('organization')
        ->and($card->fresh()->status)->toBe(CardStatus::Inactive);
});
