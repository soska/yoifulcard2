<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Program;
use Inertia\Testing\AssertableInertia as Assert;

test('member can create a card', function () {
    [$user, $organization, $program] = cardOwner();

    $this->actingAs($user)
        ->get(route('cards.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cards/create')
            ->where('usage.used', 0)
            ->where('usage.limit', null));

    $response = $this->actingAs($user)->post(route('cards.store'), [
        'initial_balance' => '0',
        'email' => 'holder@example.com',
    ]);

    $card = Card::sole();
    $response->assertRedirect(route('cards.show', $card));

    expect($card->program_id)->toBe($program->id)
        ->and($card->code)->toMatch('/^YGFT-[A-Z0-9]{4}$/')
        ->and(strlen($card->qr_token))->toBe(64)
        ->and($card->balance)->toBe('0.00')
        ->and($card->status)->toBe(CardStatus::Active)
        ->and($card->email)->toBe('holder@example.com')
        ->and($card->last_used_at)->toBeNull();
});

test('email is optional when creating a card', function () {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0.00', 'email' => ''])
        ->assertSessionHasNoErrors();

    expect(Card::sole()->email)->toBeNull();
});

test('card creation validates the initial balance and email', function (array $input, string $error) {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->from(route('cards.create'))
        ->post(route('cards.store'), $input)
        ->assertRedirect(route('cards.create'))
        ->assertSessionHasErrors($error);

    expect(Card::count())->toBe(0);
})->with([
    'missing balance' => [['email' => null], 'initial_balance'],
    'negative balance' => [['initial_balance' => '-1'], 'initial_balance'],
    'three decimals' => [['initial_balance' => '1.005'], 'initial_balance'],
    'nonzero balance before the ledger' => [['initial_balance' => '25.00'], 'initial_balance'],
    'bad email' => [['initial_balance' => '0', 'email' => 'not-an-email'], 'email'],
]);

test('card creation is refused at the card limit', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 2]);
    Card::factory()->count(2)->for($program)->create();

    $this->actingAs($user)
        ->get(route('cards.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.used', 2)
            ->where('usage.limit', 2)
            ->where('usage.atLimit', true));

    $this->actingAs($user)
        ->from(route('cards.create'))
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertRedirect(route('cards.create'))
        ->assertSessionHasErrors(['card_limit' => 'You have reached your plan limit of 2 cards. Contact support to raise the limit.']);

    expect($organization->cards()->count())->toBe(2);
});

test('usage warns at 80 percent before the limit', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 5]);
    Card::factory()->count(4)->for($program)->create();

    $this->actingAs($user)
        ->get(route('cards.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('usage.percent', 80)
            ->where('usage.nearLimit', true)
            ->where('usage.atLimit', false));

    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors();

    expect($organization->cardUsage())->toMatchArray(['used' => 5, 'atLimit' => true, 'nearLimit' => false]);
});

test('null card limit is unlimited', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => null]);
    Card::factory()->count(30)->for($program)->create();

    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($organization->cards()->count())->toBe(31)
        ->and($organization->cardUsage())->toMatchArray([
            'used' => 31, 'limit' => null, 'percent' => null, 'nearLimit' => false, 'atLimit' => false,
        ]);
});

test('cards count against the limit across all of the organization programs only', function () {
    [$user, $organization, $program] = cardOwner(['card_limit' => 2]);
    $this->travel(1)->minute();
    $second = Program::factory()->for($organization)->create();
    Card::factory()->for($second)->create();
    Card::factory()->count(5)->create();

    expect($organization->cardUsage()['used'])->toBe(1);

    $this->actingAs($user)
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertSessionHasNoErrors();

    expect($program->cards()->count())->toBe(1)
        ->and($second->cards()->count())->toBe(1);
});
