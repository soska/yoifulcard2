<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CardStatus;
use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Mail\CardLinkMail;
use App\Models\Card;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Hands a card to its customer: the public link, for staff to copy or share,
 * and an email with that link.
 *
 * Like the QR images, the link comes from its own members-only route and
 * never travels in page props, so the token stays out of Inertia's page data
 * and history.
 */
class CardLinkController extends Controller
{
    /**
     * The card's public URL, as plain text.
     */
    public function show(Card $card): Response
    {
        Gate::authorize('view', $card);

        return response($card->qrPayload(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Email the public URL to the cardholder email saved on the card. The
     * email is queued, in the sender's language.
     */
    public function email(Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        if ($card->status === CardStatus::Cancelled) {
            throw ValidationException::withMessages([
                'link' => __('A cancelled card cannot be sent.'),
            ]);
        }

        if ($card->email === null) {
            throw ValidationException::withMessages([
                'link' => __('Save a cardholder email first.'),
            ]);
        }

        Mail::to($card->email)
            ->locale(app()->getLocale())
            ->queue(new CardLinkMail($card));

        Flash::success(FlashMessage::CardLinkSent, ['email' => $card->email]);

        return back();
    }
}
