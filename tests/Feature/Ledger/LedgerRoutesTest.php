<?php

use App\Enums\CardStatus;
use App\Enums\TransactionType;
use App\Exceptions\LedgerException;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use Inertia\Testing\AssertableInertia as Assert;

test('initial balance creates a load transaction', function () {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '25.50', 'email' => ''])
        ->assertSessionHasNoErrors();

    $card = Card::sole();
    $transaction = Transaction::sole();

    expect($card->balance)->toBe('25.50')
        ->and($card->last_used_at)->not->toBeNull()
        ->and($transaction->card_id)->toBe($card->id)
        ->and($transaction->type)->toBe(TransactionType::Load)
        ->and($transaction->amount)->toBe('25.50')
        ->and($transaction->balance_after)->toBe('25.50')
        ->and($transaction->performed_by)->toBe($user->id);

    // A zero initial balance writes no transaction.
    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors();

    expect(Card::count())->toBe(2)
        ->and(Transaction::count())->toBe(1);
});

test('initial balance load rolls back the card when it fails', function () {
    [$user] = cardOwner();

    // Force the ledger to refuse inside the same database transaction.
    app()->instance(CardLedger::class, new class extends CardLedger
    {
        public function load(Card $card, string $amount, User $user, ?string $note = null): Transaction
        {
            throw LedgerException::organizationNotWritable();
        }
    });

    $this->actingAs($user)
        ->from(route('cards.create'))
        ->post(route('cards.store'), ['initial_balance' => '10.00'])
        ->assertRedirect(route('cards.create'))
        ->assertSessionHasErrors('organization');

    expect(Card::count())->toBe(0)
        ->and(Transaction::count())->toBe(0);
});

test('member can add funds, charge, and adjust from the card page', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '10.00']);

    $this->actingAs($user)->from(route('cards.show', $card));

    $this->post(route('cards.load', $card), ['amount' => '15.00', 'note' => 'Top up'])
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasNoErrors();
    $this->post(route('cards.spend', $card), ['amount' => '7.50'])
        ->assertSessionHasNoErrors();
    $this->post(route('cards.adjust', $card), ['amount' => '-2.50', 'note' => 'Wrong price'])
        ->assertSessionHasNoErrors();

    expect($card->fresh()->balance)->toBe('15.00')
        ->and(Transaction::count())->toBe(3);

    $this->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cards/show')
            ->where('card.balance', '15.00')
            ->where('transactionCount', 3)
            ->has('transactions', 3)
            ->where('transactions.0.type', 'adjustment')
            ->where('transactions.0.amount', '-2.50')
            ->where('transactions.0.balance_after', '15.00')
            ->where('transactions.0.note', 'Wrong price')
            ->where('transactions.0.performed_by', $user->name)
            ->where('transactions.2.type', 'load'));
});

test('ledger refusals come back as inline errors', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '10.00']);

    $this->actingAs($user)->from(route('cards.show', $card));

    $this->post(route('cards.spend', $card), ['amount' => '10.01'])
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasErrors(['amount' => 'Insufficient balance.']);
    $this->post(route('cards.adjust', $card), ['amount' => '-11', 'note' => 'Too far'])
        ->assertSessionHasErrors(['amount' => 'The adjustment would leave a negative balance.']);
    $this->post(route('cards.adjust', $card), ['amount' => '5'])
        ->assertSessionHasErrors(['note' => 'A note is required for adjustments.']);
    $this->post(route('cards.adjust', $card), ['amount' => '0', 'note' => 'Nothing'])
        ->assertSessionHasErrors('amount');
    $this->post(route('cards.load', $card), ['amount' => '0'])->assertSessionHasErrors('amount');
    $this->post(route('cards.load', $card), ['amount' => '-5'])->assertSessionHasErrors('amount');
    $this->post(route('cards.load', $card), ['amount' => '1.005'])->assertSessionHasErrors('amount');
    $this->post(route('cards.load', $card), ['amount' => 'abc'])->assertSessionHasErrors('amount');

    $card->update(['status' => CardStatus::Frozen]);
    $this->post(route('cards.load', $card), ['amount' => '1'])
        ->assertSessionHasErrors(['card' => 'This card is frozen.']);

    expect($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
});

test('member of another organization cannot post to a card', function () {
    [, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '10.00']);
    [$outsider] = cardOwner();

    $this->actingAs($outsider);

    $this->post(route('cards.load', $card), ['amount' => '5.00'])->assertForbidden();
    $this->post(route('cards.spend', $card), ['amount' => '5.00'])->assertForbidden();
    $this->post(route('cards.adjust', $card), ['amount' => '5.00', 'note' => 'Nope'])->assertForbidden();
    // Refused before validation, so an empty body says nothing either.
    $this->post(route('cards.spend', $card), [])->assertForbidden();

    expect($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);
});

test('guests cannot post to a card', function () {
    $card = Card::factory()->create(['balance' => '10.00']);

    $this->post(route('cards.spend', $card), ['amount' => '5.00'])->assertRedirect(route('login'));

    expect($card->fresh()->balance)->toBe('10.00');
});

test('suspended organization cannot load, spend, or adjust through the routes', function (string $status) {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '10.00']);
    $organization->update(['status' => $status]);

    $this->actingAs($user)->from(route('cards.show', $card));

    $this->post(route('cards.load', $card), ['amount' => '5.00'])
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasErrors(['organization' => 'This business is suspended. Contact support.']);
    $this->post(route('cards.spend', $card), ['amount' => '5.00'])->assertSessionHasErrors('organization');
    $this->post(route('cards.adjust', $card), ['amount' => '5.00', 'note' => 'x'])->assertSessionHasErrors('organization');

    expect($card->fresh()->balance)->toBe('10.00')
        ->and(Transaction::count())->toBe(0);

    // Reading still works.
    $this->get(route('cards.show', $card))->assertOk();
    $this->get(route('transactions.index'))->assertOk();
    $this->get(route('transactions.export'))->assertOk();
})->with(['suspended', 'cancelled']);

test('card policy refuses ledger posts to a suspended organization even without the middleware', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    expect($user->can('transact', $card))->toBeTrue();

    Organization::query()->whereKey($organization->id)->update(['status' => 'suspended']);

    expect($user->can('transact', $card->fresh()))->toBeFalse();
});
