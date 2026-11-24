<?php

use App\Enums\CardStatus;
use App\Enums\EarnMode;
use App\Enums\MembershipRole;
use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * A business with rewards on and points that expire after `$months` months
 * idle, and a card with `$points` last used `$idleMonths` months ago.
 *
 * @param  array<string, mixed>  $card
 * @param  array<string, mixed>  $organization
 * @return array{0: User, 1: Card, 2: Program, 3: Organization}
 */
function expirySetup(int $points = 10, int $idleMonths = 7, ?int $months = 6, array $card = [], array $organization = []): array
{
    $user = User::factory()->create();
    $organization = Organization::factory()->withMember($user, MembershipRole::Owner)->create($organization);
    $program = Program::factory()->for($organization)->withRewards(EarnMode::Visits)->create(['points_expiry_months' => $months]);
    $card = Card::factory()->forOrganization($organization)->create([
        'balance' => '12.00',
        'points' => $points,
        'last_used_at' => now()->subMonths($idleMonths),
        ...$card,
    ]);

    return [$user, $card, $program, $organization];
}

// --- The ledger

test('an idle card loses its points to a system entry', function () {
    [, $card] = expirySetup(10, 7, 6);
    $lastUsed = $card->last_used_at;

    $transaction = app(CardLedger::class)->expireInactive($card);

    expect($transaction)->not->toBeNull()
        ->and($transaction->type)->toBe(TransactionType::Expire)
        ->and($transaction->unit)->toBe(TransactionUnit::Points)
        ->and($transaction->amount)->toBe('10.00')
        ->and($transaction->balance_after)->toBe('0.00')
        ->and($transaction->performed_by)->toBeNull()
        ->and($transaction->note)->toBeNull();

    $fresh = $card->fresh();
    // Money, status and the last activity are not touched: expiring is not a use.
    expect($fresh->points)->toBe(0)
        ->and($fresh->balance)->toBe('12.00')
        ->and($fresh->status)->toBe(CardStatus::Active)
        ->and($fresh->last_used_at->toDateTimeString())->toBe($lastUsed->toDateTimeString());
});

test('a card used within the period keeps its points', function () {
    [, $card] = expirySetup(10, 5, 6);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and($card->fresh()->points)->toBe(10)
        ->and(Transaction::count())->toBe(0);
});

test('a card never used counts from when it was created', function () {
    [, $card] = expirySetup(10, 7, 6, ['last_used_at' => null, 'created_at' => now()->subMonths(7)]);

    expect(app(CardLedger::class)->expireInactive($card))->not->toBeNull();

    [, $recent] = expirySetup(10, 7, 6, ['last_used_at' => null, 'created_at' => now()->subMonths(2)]);

    expect(app(CardLedger::class)->expireInactive($recent))->toBeNull();
});

test('nothing expires without a rule, with rewards off, or without points', function (string $case) {
    [, $card, $program] = expirySetup();

    match ($case) {
        'no rule' => $program->update(['points_expiry_months' => null]),
        'rewards off' => $program->update(['rewards_enabled' => false]),
        'no points' => $card->update(['points' => 0]),
    };

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and(Transaction::count())->toBe(0);
})->with(['no rule', 'rewards off', 'no points']);

test('cards that cannot take changes keep their points', function (CardStatus $status) {
    [, $card] = expirySetup(10, 7, 6, ['status' => $status]);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and($card->fresh()->points)->toBe(10);
})->with([CardStatus::Frozen, CardStatus::Cancelled, CardStatus::Inactive]);

test('a suspended business\'s cards keep their points', function () {
    [, $card] = expirySetup(10, 7, 6, organization: ['status' => 'suspended']);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and($card->fresh()->points)->toBe(10);
});

test('expiring twice expires once', function () {
    [, $card] = expirySetup();

    app(CardLedger::class)->expireInactive($card);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and(Transaction::query()->where('type', TransactionType::Expire)->count())->toBe(1);
});

test('a visit that lands after the card was picked keeps its points', function () {
    [, $card] = expirySetup();

    // The command read this card as idle; a visit then touched it. The ledger
    // looks at the locked row, not at the copy it was handed.
    Card::query()->whereKey($card->id)->update(['last_used_at' => now()]);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and($card->fresh()->points)->toBe(10);
});

test('activity starts the count again', function () {
    [$user, $card] = expirySetup(10, 7, 6);

    app(CardLedger::class)->earn($card, 1, $user);

    expect(app(CardLedger::class)->expireInactive($card))->toBeNull()
        ->and($card->fresh()->points)->toBe(11);
});

// --- The command

test('the command expires every idle card and only those', function () {
    [, $idle] = expirySetup(10, 7, 6);
    [, $recent] = expirySetup(10, 1, 6);
    [, $never] = expirySetup(10, 24, null);
    [, $empty] = expirySetup(0, 24, 6);
    [, $other] = expirySetup(3, 13, 12);

    $this->artisan('rewards:expire-points')
        ->expectsOutput('Expired the points of 2 cards.')
        ->assertSuccessful();

    expect($idle->fresh()->points)->toBe(0)
        ->and($other->fresh()->points)->toBe(0)
        ->and($recent->fresh()->points)->toBe(10)
        ->and($never->fresh()->points)->toBe(10)
        ->and($empty->fresh()->points)->toBe(0)
        ->and(Transaction::query()->where('type', TransactionType::Expire)->count())->toBe(2);

    // A second run finds nothing left to expire.
    $this->artisan('rewards:expire-points')
        ->expectsOutput('Expired the points of 0 cards.')
        ->assertSuccessful();

    expect(Transaction::count())->toBe(2);
});

test('the command is on the daily schedule', function () {
    $scheduled = collect(app(Schedule::class)->events())
        ->contains(fn ($event) => str_contains((string) $event->command, 'rewards:expire-points') && $event->expression === '0 0 * * *');

    expect($scheduled)->toBeTrue();
});

test('an expiry shows in the card history as automatic, and exports without a person', function () {
    [$user, $card] = expirySetup();

    app(CardLedger::class)->expireInactive($card);

    $this->actingAs($user)->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->where('transactions.0.type', 'expire')
            ->where('transactions.0.unit', 'points')
            ->where('transactions.0.amount', '10.00')
            ->where('transactions.0.performed_by', null));

    $csv = $this->actingAs($user)->get(route('transactions.export'))->streamedContent();
    $rows = array_map(fn (string $line) => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $csv))));

    expect($rows)->toHaveCount(2)
        ->and($rows[1][2])->toBe('Expired')
        ->and($rows[1][3])->toBe('Points')
        ->and($rows[1][4])->toBe('10')
        ->and($rows[1][5])->toBe('0')
        ->and($rows[1][7])->toBe('');
});

// --- The rule in settings

test('an owner sets and clears the expiry', function () {
    [$user, , $program] = expirySetup(months: null);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits', 'points_expiry_months' => '12'])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->points_expiry_months)->toBe(12);

    $this->actingAs($user)->get(route('settings.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('program.rewards.expiry_months', 12));

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits', 'points_expiry_months' => ''])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->points_expiry_months)->toBeNull();
});

test('the expiry is validated', function (string $months) {
    [$user, , $program] = expirySetup(months: 6);

    $this->actingAs($user)->from(route('settings.edit'))
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '1', 'earn_mode' => 'visits', 'points_expiry_months' => $months])
        ->assertSessionHasErrors('points_expiry_months');

    expect($program->refresh()->points_expiry_months)->toBe(6);
})->with(['zero' => '0', 'negative' => '-1', 'a fraction' => '1.5', 'too long' => '121', 'words' => 'soon']);

test('turning rewards off keeps the expiry', function () {
    [$user, , $program] = expirySetup(months: 6);

    $this->actingAs($user)
        ->patch(route('settings.rewards.update'), ['rewards_enabled' => '0'])
        ->assertSessionHasNoErrors();

    expect($program->refresh()->rewards_enabled)->toBeFalse()
        ->and($program->points_expiry_months)->toBe(6);
});

// --- What the cardholder sees

test('the public page says when the points expire, in the business\'s local day', function () {
    // 23:30 in Mexico City on the 24th is the 25th in UTC: the local day counts.
    [, $card] = expirySetup(10, 0, 6, ['last_used_at' => Carbon::parse('2026-11-24 23:30:00', 'America/Mexico_City')], ['timezone' => 'America/Mexico_City']);

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page->where('card.points_expire_on', '2027-05-24'));
});

test('the public page has no expiry date when points will not expire', function (string $case) {
    [, $card, $program] = expirySetup(10, 1, 6);

    match ($case) {
        'no rule' => $program->update(['points_expiry_months' => null]),
        'rewards off' => $program->update(['rewards_enabled' => false]),
        'no points' => $card->update(['points' => 0]),
    };

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertInertia(fn (Assert $page) => $page->where('card.points_expire_on', null));
})->with(['no rule', 'rewards off', 'no points']);
