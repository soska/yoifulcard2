<?php

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Organization;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('member can open a card', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create(['email' => 'holder@example.com']);

    $this->actingAs($user)
        ->get(route('cards.show', $card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cards/show')
            ->where('card.id', $card->id)
            ->where('card.code', $card->code)
            ->where('card.balance', '0.00')
            ->where('card.status', 'active')
            ->where('card.email', 'holder@example.com')
            ->where('card.program', $program->name));
});

test('qr svg and png require membership of the card\'s organization', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create();
    $outsider = User::factory()->create();
    Organization::factory()->withMember($outsider)->create();

    $svg = $this->actingAs($user)->get(route('cards.qr.svg', $card));
    $svg->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    expect($svg->getContent())->toContain('<svg');

    $png = $this->actingAs($user)->get(route('cards.qr.png', $card));
    $png->assertOk()->assertHeader('Content-Type', 'image/png');
    expect(substr($png->getContent(), 0, 8))->toBe("\x89PNG\r\n\x1a\n");

    $this->actingAs($outsider)->get(route('cards.qr.svg', $card))->assertForbidden();
    $this->actingAs($outsider)->get(route('cards.qr.png', $card))->assertForbidden();

    auth()->logout();
    $this->get(route('cards.qr.svg', $card))->assertRedirect(route('login'));
    $this->get(route('cards.qr.png', $card))->assertRedirect(route('login'));
});

test('qr image encodes the public card url', function () {
    config(['app.url' => 'https://yoiful.test']);
    $card = Card::factory()->create(['qr_token' => str_repeat('x', 64)]);

    expect($card->qrPayload())->toBe('https://yoiful.test/c/'.str_repeat('x', 64));
});

test('png download is named after the card code', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create(['code' => 'YGFT-Q7R2']);

    $this->actingAs($user)
        ->get(route('cards.qr.png', $card))
        ->assertOk()
        ->assertDownload('YGFT-Q7R2.png')
        ->assertHeader('Content-Disposition', 'attachment; filename="YGFT-Q7R2.png"');
});

test('member of another organization gets 403 on a card', function () {
    [, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create();
    [$outsider] = cardOwner();

    $this->actingAs($outsider)->get(route('cards.show', $card))->assertForbidden();
    $this->actingAs($outsider)->post(route('cards.freeze', $card))->assertForbidden();
    $this->actingAs($outsider)->post(route('cards.unfreeze', $card))->assertForbidden();
    $this->actingAs($outsider)->patch(route('cards.email', $card), ['email' => 'x@example.com'])->assertForbidden();

    $card->refresh();
    expect($card->status)->toBe(CardStatus::Active)
        ->and($card->email)->toBeNull();

    // And the card is not in their list.
    $this->actingAs($outsider)
        ->get(route('cards.index'))
        ->assertInertia(fn (Assert $page) => $page->has('cards.data', 0));
});

test('unknown or malformed card ids return not found', function () {
    [$user] = cardOwner();

    $this->actingAs($user)->get('/cards/00000000-0000-0000-0000-000000000000')->assertNotFound();
    $this->actingAs($user)->get('/cards/not-a-uuid')->assertNotFound();
});

test('member can freeze and unfreeze a card', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create();

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.freeze', $card))
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasNoErrors();
    expect($card->refresh()->status)->toBe(CardStatus::Frozen);

    // Freezing twice is refused.
    $this->actingAs($user)->post(route('cards.freeze', $card))->assertSessionHasErrors('status');

    $this->actingAs($user)->post(route('cards.unfreeze', $card))->assertSessionHasNoErrors();
    expect($card->refresh()->status)->toBe(CardStatus::Active);

    $this->actingAs($user)->post(route('cards.unfreeze', $card))->assertSessionHasErrors('status');

    $cancelled = Card::factory()->for($program)->create(['status' => CardStatus::Cancelled]);
    $this->actingAs($user)->post(route('cards.freeze', $cancelled))->assertSessionHasErrors('status');
    expect($cancelled->refresh()->status)->toBe(CardStatus::Cancelled);
});

test('member can set and clear the cardholder email', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create();

    $this->actingAs($user)
        ->patch(route('cards.email', $card), ['email' => 'holder@example.com'])
        ->assertSessionHasNoErrors();
    expect($card->refresh()->email)->toBe('holder@example.com');

    $this->actingAs($user)
        ->patch(route('cards.email', $card), ['email' => 'nope'])
        ->assertSessionHasErrors('email');
    expect($card->refresh()->email)->toBe('holder@example.com');

    $this->actingAs($user)
        ->patch(route('cards.email', $card), ['email' => ''])
        ->assertSessionHasNoErrors();
    expect($card->refresh()->email)->toBeNull();
});

test('suspended organization cannot create, freeze, unfreeze, or set email', function (string $status) {
    [$user, $organization, $program] = cardOwner();
    $active = Card::factory()->for($program)->create();
    $frozen = Card::factory()->for($program)->frozen()->create();
    $organization->update(['status' => $status]);

    $this->actingAs($user);

    $this->from(route('cards.index'))
        ->post(route('cards.store'), ['initial_balance' => '0'])
        ->assertRedirect(route('cards.index'))
        ->assertSessionHasErrors('organization');
    $this->post(route('cards.freeze', $active))->assertSessionHasErrors('organization');
    $this->post(route('cards.unfreeze', $frozen))->assertSessionHasErrors('organization');
    $this->patch(route('cards.email', $active), ['email' => 'holder@example.com'])->assertSessionHasErrors('organization');

    expect(Card::count())->toBe(2)
        ->and($active->refresh()->status)->toBe(CardStatus::Active)
        ->and($active->email)->toBeNull()
        ->and($frozen->refresh()->status)->toBe(CardStatus::Frozen);

    // Reading still works.
    $this->get(route('cards.index'))->assertOk();
    $this->get(route('cards.show', $active))->assertOk();
    $this->get(route('cards.qr.png', $active))->assertOk();
})->with(['suspended', 'cancelled']);

test('card policy refuses writes to a suspended organization even without the middleware', function () {
    [$user, $organization, $program] = cardOwner();
    $card = Card::factory()->for($program)->create();

    expect($user->can('update', $card))->toBeTrue()
        ->and($user->can('create', [Card::class, $organization]))->toBeTrue();

    $organization->update(['status' => 'suspended']);
    $card->refresh();

    expect($user->can('view', $card))->toBeTrue()
        ->and($user->can('update', $card))->toBeFalse()
        ->and($user->can('create', [Card::class, $organization->refresh()]))->toBeFalse();
});
