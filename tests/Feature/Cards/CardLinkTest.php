<?php

use App\Mail\CardLinkMail;
use App\Models\Card;
use App\Models\Organization;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    RateLimiter::clear('card-link-email');
    Mail::fake();
});

test('the card link is plain text for members only', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create();
    $outsider = User::factory()->create();
    Organization::factory()->withMember($outsider)->create();

    $this->actingAs($user)
        ->get(route('cards.link', $card))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=utf-8')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertContent($card->qrPayload());

    $this->actingAs($outsider)->get(route('cards.link', $card))->assertForbidden();

    auth()->logout();
    $this->get(route('cards.link', $card))->assertRedirect(route('login'));
});

test('emailing the link queues it to the cardholder in the sender\'s language', function () {
    [$user, , $program] = cardOwner();
    $user->forceFill(['locale' => 'es'])->save();
    $card = Card::factory()->for($program)->create(['email' => 'holder@example.com']);

    $this->actingAs($user)
        ->from(route('cards.show', $card))
        ->post(route('cards.link.email', $card))
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasNoErrors()
        ->assertInertiaFlash('toast', [
            'type' => 'success',
            'code' => 'card.link_sent',
            'params' => ['email' => 'holder@example.com'],
        ]);

    Mail::assertQueued(CardLinkMail::class, fn (CardLinkMail $mail) => $mail->hasTo('holder@example.com')
        && $mail->locale === 'es'
        && $mail->card->is($card));
});

test('the link email names the business, the balance, and the card link', function (string $locale, string $subject, string $balance) {
    [, , $program] = cardOwner(['name' => 'Café Luna', 'currency' => 'MXN']);
    $card = Card::factory()->for($program)->create(['balance' => '500.00']);

    $mail = (new CardLinkMail($card))->locale($locale);

    $mail->assertHasSubject($subject);
    $mail->assertSeeInHtml('Café Luna', false);
    $mail->assertSeeInHtml($balance, false);
    $mail->assertSeeInHtml($card->qrPayload(), false);
})->with([
    'english' => ['en', 'Your Café Luna gift card', 'MX$500.00'],
    'spanish' => ['es', 'Tu tarjeta de regalo de Café Luna', '$500.00'],
]);

test('the link is not emailed without a cardholder email or to a cancelled or inactive card', function () {
    [$user, , $program] = cardOwner();
    $noEmail = Card::factory()->for($program)->create(['email' => null]);
    $cancelled = Card::factory()->for($program)->create(['email' => 'holder@example.com', 'status' => 'cancelled']);
    $inactive = Card::factory()->for($program)->inactive()->create(['email' => 'holder@example.com']);

    $this->actingAs($user)
        ->post(route('cards.link.email', $noEmail))
        ->assertSessionHasErrors(['link' => 'Save a cardholder email first.']);

    $this->post(route('cards.link.email', $cancelled))
        ->assertSessionHasErrors(['link' => 'A cancelled card cannot be sent.']);

    $this->post(route('cards.link.email', $inactive))
        ->assertSessionHasErrors(['link' => 'Activate this card before sending it.']);

    Mail::assertNothingQueued();
});

test('only members of an active organization can email the link', function () {
    [$user, $organization, $program] = cardOwner();
    $card = Card::factory()->for($program)->create(['email' => 'holder@example.com']);
    $outsider = User::factory()->create();
    Organization::factory()->withMember($outsider)->create();

    $this->actingAs($outsider)->post(route('cards.link.email', $card))->assertForbidden();

    $organization->update(['status' => 'suspended']);

    $this->actingAs($user)
        ->post(route('cards.link.email', $card))
        ->assertSessionHasErrors('organization');

    Mail::assertNothingQueued();
});

test('a card\'s link email is rate limited per card', function () {
    [$user, , $program] = cardOwner();
    $card = Card::factory()->for($program)->create(['email' => 'holder@example.com']);
    $other = Card::factory()->for($program)->create(['email' => 'other@example.com']);

    $this->actingAs($user)->from(route('cards.show', $card));

    for ($i = 0; $i < AppServiceProvider::CARD_LINK_EMAILS_PER_HOUR; $i++) {
        $this->post(route('cards.link.email', $card))->assertSessionHasNoErrors();
    }

    $this->post(route('cards.link.email', $card))
        ->assertRedirect(route('cards.show', $card))
        ->assertSessionHasErrors(['link' => 'This card was emailed several times in the last hour. Try again later.']);

    // Another card is not blocked.
    $this->post(route('cards.link.email', $other))->assertSessionHasNoErrors();

    Mail::assertQueued(CardLinkMail::class, AppServiceProvider::CARD_LINK_EMAILS_PER_HOUR + 1);
});
