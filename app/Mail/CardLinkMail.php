<?php

namespace App\Mail;

use App\Models\Card;
use App\Support\Locales;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Number;

/**
 * The card's public link, sent to its cardholder email. It is rendered when
 * the queue sends it, in the locale the sender set, so the balance is the
 * balance at sending time.
 */
class CardLinkMail extends Mailable implements ShouldQueue
{
    use Queueable;
    use SerializesModels;

    public function __construct(public Card $card) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your :business gift card', ['business' => $this->card->organization()->name]),
        );
    }

    public function content(): Content
    {
        $organization = $this->card->organization();

        return new Content(
            markdown: 'mail.card-link',
            with: [
                'business' => $organization->name,
                'balance' => Number::currency(
                    (float) $this->card->balance,
                    in: $organization->currency,
                    locale: Locales::INTL[app()->getLocale()] ?? Locales::INTL[Locales::SUPPORTED[0]],
                ),
                'url' => $this->card->qrPayload(),
            ],
        );
    }
}
