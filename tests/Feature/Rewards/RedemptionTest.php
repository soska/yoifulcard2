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
use App\Models\Reward;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A member of a new business with rewards on, a card with the given points,
 * and a reward.
 *
 * @param  array<string, mixed>  $reward
 * @return array{0: User, 1: Card, 2: Reward, 3: Program}
 */
function redemptionSetup(int $points = 25, int $cost = 10, MembershipRole $role = MembershipRole::Employee, array $reward = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, $role)->create();
    $program = Program::factory()->for($organization)->withRewards(EarnMode::Visits)->create();
    $reward = Reward::factory()->for($program)->create(['name' => 'Free coffee', 'points_cost' => $cost, ...$reward]);
    $card = Card::factory()->forOrganization($organization)->create(['points' => $points]);

    return [$user, $card, $reward, $program];
}

function redemptionRefusal(Closure $call): LedgerException
{
    try {
        $call();
    } catch (LedgerException $exception) {
        return $exception;
    }

    throw new RuntimeException('The ledger accepted a change it should have refused.');
}

// --- The ledger

test('redeeming a reward spends its cost and keeps the reward\'s name', function () {
    [$user, $card, $reward] = redemptionSetup(25, 10);

    Carbon\Carbon::setTestNow('2026-11-24 09:00:00');
    $transaction = app(CardLedger::class)->redeemReward($card, $reward, $user);

    expect($transaction->type)->toBe(TransactionType::Redeem)
        ->and($transaction->unit)->toBe(TransactionUnit::Points)
        ->and($transaction->amount)->toBe('10.00')
        ->and($transaction->balance_after)->toBe('15.00')
        ->and($transaction->reward_id)->toBe($reward->id)
        ->and($transaction->reward_name)->toBe('Free coffee')
        ->and($card->fresh()->points)->toBe(15)
        ->and($card->fresh()->last_used_at->toDateTimeString())->toBe('2026-11-24 09:00:00');

    Carbon\Carbon::setTestNow();
});

test('the history keeps the reward as it was redeemed', function () {
    [$user, $card, $reward] = redemptionSetup(25, 10);

    $transaction = app(CardLedger::class)->redeemReward($card, $reward, $user);

    $reward->update(['name' => 'Free large coffee', 'points_cost' => 20, 'is_active' => false]);

    $row = $transaction->fresh();
    expect($row->reward_name)->toBe('Free coffee')
        ->and($row->amount)->toBe('10.00');

    // Even a removed reward leaves the entry readable.
    $reward->delete();

    expect($row->fresh()->reward_id)->toBeNull()
        ->and($row->fresh()->reward_name)->toBe('Free coffee');
});

test('a redemption is refused when the card has too few points', function () {
    [$user, $card, $reward] = redemptionSetup(9, 10);

    $refusal = redemptionRefusal(fn () => app(CardLedger::class)->redeemReward($card, $reward, $user));

    expect($refusal->getMessage())->toBe('Not enough points.')
        ->and($refusal->field)->toBe('points')
        ->and($card->fresh()->points)->toBe(9)
        ->and(Transaction::count())->toBe(0);
});

test('a card can redeem with exactly the points it has', function () {
    [$user, $card, $reward] = redemptionSetup(10, 10);

    app(CardLedger::class)->redeemReward($card, $reward, $user);

    expect($card->fresh()->points)->toBe(0)
        ->and($card->fresh()->status)->toBe(CardStatus::Active);
});

test('an inactive reward or another program\'s reward is not available', function () {
    [$user, $card, $reward] = redemptionSetup();
    $reward->update(['is_active' => false]);
    $other = Reward::factory()->create();

    foreach ([$reward, $other] as $unavailable) {
        $refusal = redemptionRefusal(fn () => app(CardLedger::class)->redeemReward($card, $unavailable, $user));

        expect($refusal->getMessage())->toBe('That reward is not available.')
            ->and($refusal->field)->toBe('reward');
    }

    expect($card->fresh()->points)->toBe(25)
        ->and(Transaction::count())->toBe(0);
});

test('a reward cost raised after it was chosen is the cost charged', function () {
    [$user, $card, $reward] = redemptionSetup(25, 10);
    $chosen = Reward::query()->find($reward->id);

    // The reward is edited while the staff member has the old one on screen.
    $reward->update(['points_cost' => 20]);

    app(CardLedger::class)->redeemReward($card, $chosen, $user);

    expect($card->fresh()->points)->toBe(5);
});

test('redeeming gets the same refusals as any points change', function (CardStatus $status, string $message) {
    [$user, $card, $reward] = redemptionSetup();
    $card->update(['status' => $status]);

    $refusal = redemptionRefusal(fn () => app(CardLedger::class)->redeemReward($card, $reward, $user));

    expect($refusal->getMessage())->toBe($message)
        ->and($card->fresh()->points)->toBe(25);
})->with([
    'frozen' => [CardStatus::Frozen, 'This card is frozen.'],
    'cancelled' => [CardStatus::Cancelled, 'This card is cancelled.'],
    'inactive' => [CardStatus::Inactive, 'This card is not activated yet.'],
]);

test('redeeming is refused when the program turned rewards off', function () {
    [$user, $card, $reward, $program] = redemptionSetup();
    $program->update(['rewards_enabled' => false]);

    $refusal = redemptionRefusal(fn () => app(CardLedger::class)->redeemReward($card, $reward, $user));

    expect($refusal->field)->toBe('rewards')
        ->and($card->fresh()->points)->toBe(25);
});

// --- The reader

test('staff redeem a reward from the reader, and the toast names it', function () {
    [$user, $card, $reward] = redemptionSetup(25, 10);

    $this->actingAs($user)
        ->post(route('cards.redeem', $card), ['reward_id' => $reward->id, 'reader' => true])
        ->assertRedirect(route('scan'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'ledger.reward_redeemed',
            'params' => ['code' => $card->code, 'points' => '10', 'total' => '15', 'reward' => 'Free coffee'],
        ]);

    expect($card->fresh()->points)->toBe(15);
});

test('a redemption without enough points comes back as an inline error', function () {
    [$user, $card, $reward] = redemptionSetup(3, 10);

    $this->actingAs($user)->from(route('scan.cards.show', $card))
        ->post(route('cards.redeem', $card), ['reward_id' => $reward->id, 'reader' => true])
        ->assertSessionHasErrors(['points' => 'Not enough points.']);

    expect($card->fresh()->points)->toBe(3);
});

test('an unknown or missing reward is refused', function () {
    [$user, $card] = redemptionSetup();

    $this->actingAs($user)->from(route('scan.cards.show', $card));

    $this->post(route('cards.redeem', $card), ['reward_id' => (string) Str::uuid()])
        ->assertSessionHasErrors(['reward' => 'That reward is not available.']);
    $this->post(route('cards.redeem', $card), [])
        ->assertSessionHasErrors('reward_id');
    $this->post(route('cards.redeem', $card), ['reward_id' => 'nope'])
        ->assertSessionHasErrors('reward_id');
});

test('another business\'s member cannot redeem on a card', function () {
    [, $card, $reward] = redemptionSetup();
    [$stranger] = redemptionSetup();

    $this->actingAs($stranger)
        ->post(route('cards.redeem', $card), ['reward_id' => $reward->id])
        ->assertForbidden();

    expect($card->fresh()->points)->toBe(25);
});

test('redeeming is refused while the business is suspended', function () {
    [$user, $card, $reward, $program] = redemptionSetup();
    $program->organization->update(['status' => 'suspended']);

    $this->actingAs($user)->post(route('cards.redeem', $card), ['reward_id' => $reward->id]);

    expect($card->fresh()->points)->toBe(25)
        ->and(Transaction::count())->toBe(0);
});

test('the reader gets the active rewards, cheapest first', function () {
    [$user, $card, , $program] = redemptionSetup(25, 10);
    Reward::factory()->for($program)->create(['name' => 'Free pastry', 'points_cost' => 5]);
    Reward::factory()->for($program)->inactive()->create(['name' => 'Retired', 'points_cost' => 1]);
    Reward::factory()->create(['name' => 'Someone else\'s']);

    $this->actingAs($user)->get(route('scan.cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reader/card')
            ->has('rewardOptions', 2)
            ->where('rewardOptions.0.name', 'Free pastry')
            ->where('rewardOptions.0.points_cost', 5)
            ->where('rewardOptions.1.name', 'Free coffee')
            ->has('rewardOptions.0.id'));
});

test('the reader gets no rewards while the program has rewards off', function () {
    [$user, $card, , $program] = redemptionSetup();
    $program->update(['rewards_enabled' => false]);

    $this->actingAs($user)->get(route('scan.cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page->has('rewardOptions', 0));
});

// --- The public card page

test('the public page lists the active rewards with names and costs only', function () {
    [, $card, , $program] = redemptionSetup(4, 10);
    Reward::factory()->for($program)->create(['name' => 'Free pastry', 'points_cost' => 5]);
    Reward::factory()->for($program)->inactive()->create(['name' => 'Retired']);

    $response = $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rewards', [
                ['name' => 'Free pastry', 'points_cost' => 5],
                ['name' => 'Free coffee', 'points_cost' => 10],
            ]));

    // Nothing else about the rewards or the program leaks.
    $json = json_encode($response->viewData('page')['props']);
    expect($json)->not->toContain($program->id)
        ->and($json)->not->toContain('Retired');

    $program->update(['rewards_enabled' => false]);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page->where('rewards', []));
});

// --- History

test('a redemption shows in the card history and the transactions page with its reward', function () {
    [$user, $card, $reward] = redemptionSetup(25, 10, MembershipRole::Owner);

    app(CardLedger::class)->redeemReward($card, $reward, $user);

    $this->actingAs($user)->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.type', 'redeem')
            ->where('transactions.0.unit', 'points')
            ->where('transactions.0.reward_name', 'Free coffee')
            ->where('transactions.0.amount', '10.00'));

    $this->actingAs($user)->get(route('transactions.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('transactions.data', 1)
            ->where('transactions.data.0.reward_name', 'Free coffee'));
});
