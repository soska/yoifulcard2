<?php

use App\Enums\CardStatus;
use App\Enums\EarnMode;
use App\Enums\MembershipRole;
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
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member of a new business whose program has rewards on, and a card.
 *
 * @param  array<string, mixed>  $organization
 * @param  array<string, mixed>  $card
 * @return array{0: User, 1: Card, 2: Program, 3: Organization}
 */
function rewardsSetup(EarnMode $mode = EarnMode::Spend, string $rate = '1.00', MembershipRole $role = MembershipRole::Owner, array $organization = [], array $card = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create($organization);
    $program = Program::factory()->for($organization)->withRewards($mode, $rate)->create();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '100.00', ...$card]);

    return [$user, $card, $program, $organization];
}

function rewardsRefusal(Closure $call): LedgerException
{
    try {
        $call();
    } catch (LedgerException $exception) {
        return $exception;
    }

    throw new RuntimeException('The ledger accepted a change it should have refused.');
}

// --- The ledger: earning by the program's rule

test('spend mode earns whole points for an amount, rounded down', function (string $rate, string $amount, int $points) {
    [$user, $card] = rewardsSetup(EarnMode::Spend, $rate);

    $transaction = app(CardLedger::class)->earnForPurchase($card, $amount, $user);

    expect($transaction->type)->toBe(TransactionType::Earn)
        ->and($transaction->unit)->toBe(TransactionUnit::Points)
        ->and($card->fresh()->points)->toBe($points)
        ->and($card->fresh()->balance)->toBe('100.00');
})->with([
    'one per unit' => ['1.00', '25.50', 25],
    'two per unit' => ['2.00', '25.50', 51],
    'a fraction rate' => ['0.50', '25.50', 12],
    'under a whole point' => ['1.00', '3.99', 3],
]);

test('spend mode needs an amount that earns at least a point', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00');

    $missing = rewardsRefusal(fn () => app(CardLedger::class)->earnForPurchase($card, null, $user));
    $tooSmall = rewardsRefusal(fn () => app(CardLedger::class)->earnForPurchase($card, '0.40', $user));

    expect($missing->getMessage())->toBe('Enter the purchase amount.')
        ->and($missing->field)->toBe('amount')
        ->and($tooSmall->getMessage())->toBe('That amount is too small to earn points.')
        ->and($card->fresh()->points)->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

test('visits mode earns one point a visit and stops at the daily cap', function () {
    [$user, $card] = rewardsSetup(EarnMode::Visits);
    $cap = CardLedger::VISIT_POINTS_PER_DAY;

    foreach (range(1, $cap) as $visit) {
        app(CardLedger::class)->earnForPurchase($card, null, $user);
    }

    $refusal = rewardsRefusal(fn () => app(CardLedger::class)->earnForPurchase($card, null, $user));

    expect($card->fresh()->points)->toBe($cap)
        ->and($refusal->field)->toBe('card')
        ->and($refusal->getMessage())->toBe("This card already earned its {$cap} visit points today.");
});

test('the daily cap resets at the business\'s local midnight', function () {
    // 23:30 in Mexico City is already the next day in UTC.
    [$user, $card] = rewardsSetup(EarnMode::Visits, organization: ['timezone' => 'America/Mexico_City']);

    Carbon::setTestNow(Carbon::parse('2026-11-24 23:30:00', 'America/Mexico_City'));

    foreach (range(1, CardLedger::VISIT_POINTS_PER_DAY) as $visit) {
        app(CardLedger::class)->earnForPurchase($card, null, $user);
    }

    expect(fn () => app(CardLedger::class)->earnForPurchase($card, null, $user))->toThrow(LedgerException::class);

    // An hour later it is a new local day.
    Carbon::setTestNow(Carbon::parse('2026-11-25 00:30:00', 'America/Mexico_City'));
    app(CardLedger::class)->earnForPurchase($card, null, $user);

    expect($card->fresh()->points)->toBe(CardLedger::VISIT_POINTS_PER_DAY + 1);

    Carbon::setTestNow();
});

test('earning is refused until the program has a rule', function () {
    [$user, $card, $program] = rewardsSetup();
    $program->update(['earn_mode' => null]);

    $refusal = rewardsRefusal(fn () => app(CardLedger::class)->earnForPurchase($card, '10', $user));

    expect($refusal->field)->toBe('rewards')
        ->and($card->fresh()->points)->toBe(0);
});

// --- Charges earn too

test('a charge earns points by the same rule, in the same step', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '2.00');

    $transaction = app(CardLedger::class)->spend($card, '12.50', $user);

    expect($transaction->type)->toBe(TransactionType::Spend)
        ->and($card->fresh()->balance)->toBe('87.50')
        ->and($card->fresh()->points)->toBe(25)
        ->and(Transaction::query()->where('type', TransactionType::Earn)->sole()->amount)->toBe('25.00');
});

test('a charge counts as a visit in visits mode, up to the daily cap', function () {
    [$user, $card] = rewardsSetup(EarnMode::Visits);

    foreach (range(1, CardLedger::VISIT_POINTS_PER_DAY + 2) as $charge) {
        app(CardLedger::class)->spend($card, '1.00', $user);
    }

    // Every charge went through; only the visits up to the cap earned.
    expect($card->fresh()->balance)->toBe(bcsub('100.00', (string) (CardLedger::VISIT_POINTS_PER_DAY + 2), 2))
        ->and($card->fresh()->points)->toBe(CardLedger::VISIT_POINTS_PER_DAY)
        ->and(Transaction::query()->where('type', TransactionType::Spend)->count())->toBe(CardLedger::VISIT_POINTS_PER_DAY + 2);
});

test('a charge too small to earn points still goes through', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00');

    app(CardLedger::class)->spend($card, '0.50', $user);

    expect($card->fresh()->balance)->toBe('99.50')
        ->and($card->fresh()->points)->toBe(0)
        ->and(Transaction::query()->where('type', TransactionType::Earn)->count())->toBe(0);
});

test('a charge on a program without rewards earns nothing', function () {
    [$user, $card, $program] = rewardsSetup();
    $program->update(['rewards_enabled' => false]);

    app(CardLedger::class)->spend($card, '10.00', $user);

    expect($card->fresh()->points)->toBe(0)
        ->and(Transaction::count())->toBe(1);
});

test('a refused charge earns nothing', function () {
    [$user, $card] = rewardsSetup();

    expect(fn () => app(CardLedger::class)->spend($card, '100.01', $user))->toThrow(LedgerException::class)
        ->and($card->fresh()->points)->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

test('a charge that empties the card still earns its points', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00', card: ['balance' => '10.00']);

    app(CardLedger::class)->spend($card, '10.00', $user);

    expect($card->fresh()->status)->toBe(CardStatus::Depleted)
        ->and($card->fresh()->points)->toBe(10);
});

// --- Routes

test('a member earns points from the reader, and the toast says how many', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00', MembershipRole::Employee);

    $this->actingAs($user)
        ->post(route('cards.earn', $card), ['amount' => '18.40', 'reader' => true])
        ->assertRedirect(route('scan'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'ledger.points_earned',
            'params' => ['code' => $card->code, 'points' => '18', 'total' => '18'],
        ]);

    expect($card->fresh()->points)->toBe(18);
});

test('a member records a visit with no amount', function () {
    [$user, $card] = rewardsSetup(EarnMode::Visits);

    $this->actingAs($user)
        ->post(route('cards.earn', $card), ['reader' => true])
        ->assertSessionHasNoErrors();

    expect($card->fresh()->points)->toBe(1);
});

test('earn refusals come back as inline errors', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00');

    $this->actingAs($user)->from(route('scan.cards.show', $card));

    $this->post(route('cards.earn', $card), ['amount' => '0.10'])
        ->assertSessionHasErrors(['amount' => 'That amount is too small to earn points.']);
    $this->post(route('cards.earn', $card), [])
        ->assertSessionHasErrors(['amount' => 'Enter the purchase amount.']);
    $this->post(route('cards.earn', $card), ['amount' => '-5'])
        ->assertSessionHasErrors('amount');
});

test('a charge from the reader names the points it earned in the toast', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00');

    $this->actingAs($user)
        ->post(route('cards.spend', $card), ['amount' => '30.00', 'reader' => true])
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'ledger.charged',
            'params' => [
                'code' => $card->code,
                'amount' => '30.00',
                'balance' => '70.00',
                'currency' => $card->organization()->currency,
                'points' => '30',
                'total' => '30',
            ],
        ]);
});

test('a charge without rewards keeps the toast it had', function () {
    [$user, $card, $program] = rewardsSetup();
    $program->update(['rewards_enabled' => false]);

    $this->actingAs($user)
        ->post(route('cards.spend', $card), ['amount' => '30.00', 'reader' => true])
        ->assertInertiaFlash('toast.params', [
            'code' => $card->code,
            'amount' => '30.00',
            'balance' => '70.00',
            'currency' => $card->organization()->currency,
        ]);
});

test('owners and managers adjust points; employees cannot', function (MembershipRole $role, bool $allowed) {
    [$user, $card] = rewardsSetup(role: $role);
    $card->update(['points' => 10]);

    $response = $this->actingAs($user)
        ->post(route('cards.points.adjust', $card), ['points' => '-4', 'note' => 'Wrong visit']);

    if ($allowed) {
        $response->assertSessionHasNoErrors();
        expect($card->fresh()->points)->toBe(6)
            ->and(Transaction::sole()->type)->toBe(TransactionType::PointsAdjustment);
    } else {
        $response->assertForbidden();
        expect($card->fresh()->points)->toBe(10)
            ->and(Transaction::count())->toBe(0);
    }
})->with([
    'owner' => [MembershipRole::Owner, true],
    'manager' => [MembershipRole::Manager, true],
    'employee' => [MembershipRole::Employee, false],
]);

test('a points adjustment needs a note and a nonzero whole number', function () {
    [$user, $card] = rewardsSetup();
    $card->update(['points' => 5]);

    $this->actingAs($user)->from(route('cards.show', $card));

    $this->post(route('cards.points.adjust', $card), ['points' => '3'])
        ->assertSessionHasErrors(['note' => 'A note is required for adjustments.']);
    $this->post(route('cards.points.adjust', $card), ['points' => '0', 'note' => 'Nothing'])
        ->assertSessionHasErrors('points');
    $this->post(route('cards.points.adjust', $card), ['points' => '1.5', 'note' => 'Half'])
        ->assertSessionHasErrors('points');
    $this->post(route('cards.points.adjust', $card), ['points' => '-9', 'note' => 'Too many'])
        ->assertSessionHasErrors(['points' => 'The adjustment would leave negative points.']);

    expect($card->fresh()->points)->toBe(5);
});

test('another business\'s member cannot touch a card\'s points', function () {
    [, $card] = rewardsSetup();
    [$stranger] = rewardsSetup();

    $this->actingAs($stranger)
        ->post(route('cards.earn', $card), ['amount' => '10'])
        ->assertForbidden();
    $this->actingAs($stranger)
        ->post(route('cards.points.adjust', $card), ['points' => '5', 'note' => 'Mine now'])
        ->assertForbidden();

    expect($card->fresh()->points)->toBe(0);
});

test('points routes are refused while the business is suspended', function () {
    [$user, $card] = rewardsSetup(organization: ['status' => 'suspended']);

    $this->actingAs($user)->post(route('cards.earn', $card), ['amount' => '10']);
    $this->actingAs($user)->post(route('cards.points.adjust', $card), ['points' => '5', 'note' => 'Nope']);

    expect($card->fresh()->points)->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

// --- What pages show

test('the reader, the card page and the public page show points', function () {
    [$user, $card, $program] = rewardsSetup(EarnMode::Visits);
    $card->update(['points' => 7]);

    $this->actingAs($user)->get(route('scan.cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reader/card')
            ->where('card.points', 7)
            ->where('rewards', ['enabled' => true, 'mode' => 'visits', 'rate' => null, 'visit_cap' => CardLedger::VISIT_POINTS_PER_DAY]));

    $this->actingAs($user)->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cards/show')
            ->where('card.points', 7)
            ->where('rewards.enabled', true)
            ->where('canAdjustPoints', true));

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('card.points', 7)
            ->where('rewards_enabled', true));

    $program->update(['rewards_enabled' => false]);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page->where('rewards_enabled', false));
});

test('an employee does not get the points adjustment on the card page', function () {
    [$user, $card] = rewardsSetup(role: MembershipRole::Employee);

    $this->actingAs($user)->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page->where('canAdjustPoints', false));
});

test('points entries show in the card history but stay out of the money reports', function () {
    [$user, $card] = rewardsSetup(EarnMode::Spend, '1.00');

    app(CardLedger::class)->load($card, '5.00', $user);
    app(CardLedger::class)->earnForPurchase($card, '12.00', $user);

    $this->actingAs($user)->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactionCount', 2)
            ->has('transactions', 2)
            ->where('transactions.0.type', 'earn')
            ->where('transactions.0.unit', 'points')
            ->where('transactions.0.amount', '12.00')
            ->where('transactions.1.unit', 'money'));

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.type', 'load'));

    $this->actingAs($user)->get(route('analytics'))
        ->assertInertia(fn (Assert $page) => $page->where('summary.transactions', 1));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentTransactions', 1)
            ->where('stats.monthTransactions', 1));
});
