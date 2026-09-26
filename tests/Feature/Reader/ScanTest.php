<?php

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CardLedger;
use App\Support\CurrentOrganization;
use App\Support\QrPayload;
use Inertia\Testing\AssertableInertia as Assert;

test('scan lookup resolves a token from the current organization', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    $this->actingAs($user)
        ->from(route('scan'))
        ->post(route('scan.lookup'), ['payload' => $card->qrPayload()])
        ->assertRedirect(route('scan.cards.show', $card))
        ->assertSessionHasNoErrors();

    // Surrounding whitespace, another host (a card printed from another URL
    // of the app), and a trailing slash still resolve.
    $token = $card->qr_token;

    foreach (["  {$card->qrPayload()}\n", "https://cards.example.com/c/{$token}", "http://192.168.1.20:8000/c/{$token}/"] as $payload) {
        $this->post(route('scan.lookup'), ['payload' => $payload])
            ->assertRedirect(route('scan.cards.show', $card));
    }

    $this->get(route('scan.cards.show', $card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reader/card')
            ->where('card', [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $card->balance,
                'status' => 'active',
            ])
            ->where('currency', $organization->currency))
        ->assertDontSee($token);
});

test('scan lookup of another organization\'s token returns not found', function () {
    [$user] = cardOwner();
    [, $other] = cardOwner();
    $foreign = Card::factory()->forOrganization($other)->create();

    $this->actingAs($user)
        ->from(route('scan'))
        ->post(route('scan.lookup'), ['payload' => $foreign->qrPayload()])
        ->assertRedirect(route('scan'))
        ->assertSessionHasErrors(['payload' => 'Card not found.']);

    // Opening the other organization's card in the reader is a 404 too.
    $this->get(route('scan.cards.show', $foreign))->assertNotFound();
});

test('scan lookup only searches the current organization when the user has two', function () {
    [$user, $first] = cardOwner();
    $second = Organization::factory()->create();
    Membership::factory()->for($user)->for($second)->create();
    Program::factory()->for($second)->create();
    $secondCard = Card::factory()->forOrganization($second)->create();

    $this->actingAs($user)
        ->withSession([CurrentOrganization::SESSION_KEY => $first->id])
        ->post(route('scan.lookup'), ['payload' => $secondCard->qrPayload()])
        ->assertRedirect(route('scan'))
        ->assertSessionHasErrors(['payload' => 'Card not found.']);

    $this->get(route('scan.cards.show', $secondCard))->assertNotFound();

    $this->withSession([CurrentOrganization::SESSION_KEY => $second->id])
        ->post(route('scan.lookup'), ['payload' => $secondCard->qrPayload()])
        ->assertRedirect(route('scan.cards.show', $secondCard));
});

test('scan lookup of a malformed payload returns not found', function (mixed $payload) {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    // {url}, {token} and {short} stand for the real card's QR payload, its
    // token, and the token minus its last character, so near misses of a
    // valid payload are covered too.
    if (is_string($payload)) {
        $payload = strtr($payload, [
            '{url}' => $card->qrPayload(),
            '{token}' => $card->qr_token,
            '{short}' => substr($card->qr_token, 0, -1),
        ]);
    }

    $this->actingAs($user)
        ->from(route('scan'))
        ->post(route('scan.lookup'), ['payload' => $payload])
        ->assertRedirect(route('scan'))
        ->assertSessionHasErrors(['payload' => 'Card not found.']);
})->with([
    'missing' => [null],
    'empty' => [''],
    'plain text' => ['hello world'],
    'array' => [['https://example.com/c/abc']],
    'wrong path' => ['https://example.com/cards/{token}'],
    'bare token' => ['{token}'],
    'short token' => ['https://example.com/c/{short}'],
    'long token' => ['{url}A'],
    'nested path' => ['https://example.com/app/c/{token}'],
    'bad characters' => ['https://example.com/c/'.str_repeat('*', 64)],
    'unknown token' => ['https://example.com/c/'.str_repeat('A', 64)],
    'no host' => ['/c/{token}'],
    'javascript url' => ['javascript:alert(1)//c/{token}'],
    'query string' => ['https://example.com/?next=/c/{token}'],
    'too long' => ['{url}?'.str_repeat('x', QrPayload::MAX_LENGTH)],
]);

test('reader charge and load go through CardLedger', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '20.00']);

    $ledger = $this->partialMock(CardLedger::class);

    $this->actingAs($user)->from(route('scan.cards.show', $card));

    $this->post(route('cards.spend', $card), ['amount' => '7.50', 'reader' => true])
        ->assertRedirect(route('scan'))
        ->assertSessionHasNoErrors();

    $this->post(route('cards.load', $card), ['amount' => '2.25', 'reader' => true])
        ->assertRedirect(route('scan'))
        ->assertSessionHasNoErrors();

    $ledger->shouldHaveReceived('spend')->once();
    $ledger->shouldHaveReceived('load')->once();

    $transactions = Transaction::query()->orderBy('created_at')->orderBy('type')->get();

    expect($card->fresh()->balance)->toBe('14.75')
        ->and($transactions)->toHaveCount(2)
        ->and($transactions->firstWhere('type', TransactionType::Spend)->balance_after)->toBe('12.50')
        ->and($transactions->firstWhere('type', TransactionType::Load)->balance_after)->toBe('14.75')
        ->and($transactions->pluck('performed_by')->unique()->all())->toBe([$user->id]);

    // A refused charge goes back to the reader card page with the error and
    // writes nothing.
    $this->post(route('cards.spend', $card), ['amount' => '100.00', 'reader' => true])
        ->assertRedirect(route('scan.cards.show', $card))
        ->assertSessionHasErrors('amount');

    expect($card->fresh()->balance)->toBe('14.75')
        ->and(Transaction::count())->toBe(2);
});

test('reader cannot charge a card of a suspended organization', function () {
    [$user, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create(['balance' => '20.00']);
    $organization->update(['status' => 'suspended']);

    // The card still opens, read-only; the page shows the banner from the
    // shared prop.
    $this->actingAs($user)
        ->get(route('scan.cards.show', $card))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('reader/card')
            ->where('currentOrganization.status', 'suspended'));

    $this->from(route('scan.cards.show', $card))
        ->post(route('cards.spend', $card), ['amount' => '5.00', 'reader' => true])
        ->assertRedirect(route('scan.cards.show', $card))
        ->assertSessionHasErrors('organization');

    expect($card->fresh()->balance)->toBe('20.00')
        ->and(Transaction::count())->toBe(0);
});

test('guests are redirected from scan', function () {
    [, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    $this->get(route('scan'))->assertRedirect(route('login'));
    $this->get(route('scan.cards.show', $card))->assertRedirect(route('login'));
    $this->post(route('scan.lookup'), ['payload' => $card->qrPayload()])->assertRedirect(route('login'));
});

test('members see the scanner page', function () {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->get(route('scan'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('reader/scan'));
});

test('reader card page 404s for unknown ids', function () {
    [$user] = cardOwner();

    $this->actingAs($user)
        ->get('/scan/cards/00000000-0000-0000-0000-000000000000')
        ->assertNotFound();
});

test('a user without a membership cannot look up a card', function () {
    [, $organization] = cardOwner();
    $card = Card::factory()->forOrganization($organization)->create();

    $this->actingAs(User::factory()->create())
        ->post(route('scan.lookup'), ['payload' => $card->qrPayload()])
        ->assertForbidden();
});
