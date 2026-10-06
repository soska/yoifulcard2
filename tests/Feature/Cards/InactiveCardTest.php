<?php

use App\Enums\CardStatus;
use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Transaction;
use App\Services\CardLedger;
use Inertia\Testing\AssertableInertia as Assert;

/*
| Preissued cards (ARM-356): a card that exists but can't be used until the
| business activates it with an amount. The ledger rules are in
| tests/Feature/Ledger/CardLedgerTest.php.
*/

test('inactive cards do not count toward usage; they are stock', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 5]);
    Card::factory()->count(4)->for($program)->create();
    Card::factory()->count(3)->for($program)->inactive()->create();

    expect($organization->cardUsage())->toBe([
        'used' => 4, 'limit' => 5, 'percent' => 80, 'nearLimit' => true, 'atLimit' => false, 'stock' => 3,
    ]);

    // Unlimited plans report stock too.
    $organization->update(['card_limit' => null]);
    expect($organization->cardUsage())->toMatchArray(['used' => 4, 'limit' => null, 'stock' => 3]);

    // Inactive cards do not block creating a card.
    $organization->update(['card_limit' => 5]);
    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors();

    expect($organization->cardUsage())->toMatchArray(['used' => 5, 'atLimit' => true, 'stock' => 3]);
});

test('member can activate a card from the card page', function () {
    [$user, $organization] = cardOwner(['currency' => 'MXN']);
    $card = Card::factory()->forOrganization($organization)->inactive()->create();

    $this->actingAs($user)
        ->get(route('cards.show', $card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cards/show')
            ->where('card.status', 'inactive')
            ->where('card.activated_at', null));

    $this->from(route('cards.show', $card))
        ->post(route('cards.activate', $card), ['amount' => '300'])
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'ledger.activated',
            'params' => ['code' => $card->code, 'amount' => '300.00', 'balance' => '300.00', 'currency' => 'MXN'],
        ]);

    $card->refresh();
    expect($card->status)->toBe(CardStatus::Active)
        ->and($card->balance)->toBe('300.00')
        ->and($card->activated_at)->not->toBeNull()
        ->and(Transaction::sole()->type)->toBe(TransactionType::Load);

    $this->get(route('cards.show', $card))
        ->assertInertia(fn (Assert $page) => $page
            ->where('card.status', 'active')
            ->where('card.activated_at', $card->activated_at->toIso8601String()));

    // A second activation is refused.
    $this->from(route('cards.show', $card))
        ->post(route('cards.activate', $card), ['amount' => '10'])
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasErrors(['card' => 'This card is already activated.']);
});

test('activating at the card limit fails with the plan limit message', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 1]);
    Card::factory()->for($program)->create();
    $card = Card::factory()->for($program)->inactive()->create();

    $this->actingAs($user)
        ->from(route('scan.cards.show', $card))
        ->post(route('cards.activate', $card), ['amount' => '100', 'reader' => true])
        ->assertRedirect(route('scan.cards.show', $card))
        ->assertSessionHasErrors(['card_limit' => 'You have reached your plan limit of 1 cards. Contact support to raise the limit.']);

    expect($card->fresh()->status)->toBe(CardStatus::Inactive)
        ->and(Transaction::count())->toBe(0);
});

test('the reader shows an inactive card and activates it through CardLedger', function () {
    [$user, $organization] = cardOwner(['currency' => 'MXN']);
    $card = Card::factory()->forOrganization($organization)->inactive()->create();

    $this->actingAs($user)
        ->get(route('scan.cards.show', $card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reader/card')
            ->where('card.status', 'inactive'));

    $ledger = $this->partialMock(CardLedger::class);

    $this->from(route('scan.cards.show', $card))
        ->post(route('cards.activate', $card), ['amount' => '150.00', 'reader' => true])
        ->assertRedirect(route('scan'))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'ledger.activated',
            'params' => ['code' => $card->code, 'amount' => '150.00', 'balance' => '150.00', 'currency' => 'MXN'],
        ]);

    $ledger->shouldHaveReceived('activate')->once();
    expect($card->fresh()->status)->toBe(CardStatus::Active);
});

test('the reader cannot charge or load an inactive card', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->inactive()->create();

    $this->actingAs($user)->from(route('scan.cards.show', $card));

    foreach (['cards.spend', 'cards.load'] as $route) {
        $this->post(route($route, $card), ['amount' => '5.00', 'reader' => true])
            ->assertRedirect(route('scan.cards.show', $card))
            ->assertSessionHasErrors(['card' => 'This card is not activated yet.']);
    }

    $this->from(route('cards.show', $card))
        ->post(route('cards.adjust', $card), ['amount' => '5.00', 'note' => 'Opening'])
        ->assertSessionHasErrors(['card' => 'This card is not activated yet.']);

    expect($card->fresh()->status)->toBe(CardStatus::Inactive)
        ->and(Transaction::count())->toBe(0);
});

test('activation is refused for another organization, guests, and suspended organizations', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->inactive()->create();
    [$outsider] = cardOwner();

    $this->post(route('cards.activate', $card), ['amount' => '10'])->assertRedirect(route('login'));

    $this->actingAs($outsider)
        ->post(route('cards.activate', $card), ['amount' => '10'])
        ->assertForbidden();

    $organization->update(['status' => 'suspended']);
    $this->actingAs($user)
        ->post(route('cards.activate', $card), ['amount' => '10'])
        ->assertSessionHasErrors();

    expect($card->fresh()->status)->toBe(CardStatus::Inactive);
});

test('an inactive card cannot be frozen', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->inactive()->create();

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.freeze', $card))
        ->assertSessionHasErrors(['status' => 'Only an active card can be frozen.']);

    expect($card->fresh()->status)->toBe(CardStatus::Inactive);
});

test('the card list filters by inactive', function () {
    [$user, , $program] = cardOwner();
    Card::factory()->for($program)->create(['code' => 'YGFT-AAAAAA']);
    Card::factory()->for($program)->inactive()->create(['code' => 'YGFT-BBBBBB']);

    $this->actingAs($user)
        ->get(route('cards.index', ['status' => 'inactive']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('statuses', CardStatus::values())
            ->where('filters.status', 'inactive')
            ->has('cards.data', 1)
            ->where('cards.data.0.code', 'YGFT-BBBBBB'));

    expect(CardStatus::values())->toContain('inactive');
});

test('the public page shows an inactive card as not activated', function () {
    [, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->inactive()->create();

    $this->get(route('public-card.show', ['token' => $card->qr_token]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('public-card/show')
            ->where('card.status', 'inactive')
            ->where('card.balance', '0.00'));

    // It takes no cardholder email until it is activated.
    $this->post(route('public-card.email', ['token' => $card->qr_token]), ['email' => 'finder@example.com'])
        ->assertSessionHasErrors(['email' => 'This card is not activated yet.']);

    expect($card->fresh()->email)->toBeNull();
});

test('dashboard and admin stats count inactive cards and show stock', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 10, 'preissue_limit' => 50]);
    Card::factory()->count(2)->for($program)->create();
    Card::factory()->count(3)->for($program)->inactive()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.cards', 5)
            ->where('stats.inactive', 3)
            ->where('stats.active', 2)
            ->where('usage.used', 2)
            ->where('usage.stock', 3));

    $this->actingAs(superadmin())
        ->get(route('admin.organizations.show', $organization))
        ->assertInertia(fn (Assert $page) => $page
            ->where('organization.preissue_limit', 50)
            ->where('stats.inactive', 3)
            ->where('stats.active', 2)
            ->where('usage.used', 2)
            ->where('usage.stock', 3));
});
