<?php

namespace App\Http\Controllers;

use App\Enums\CardStatus;
use App\Enums\FlashMessage;
use App\Http\Requests\PublicCard\SaveCardEmailRequest;
use App\Models\Card;
use App\Services\CardCodeGenerator;
use App\Services\CardQrCode;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cardholder's page at `/c/{token}`. No login. It shows the business
 * branding and the balance, and nothing else about the card. A card that
 * is not activated yet says so instead of showing a balance. Its QR
 * (`/c/{token}/qr.svg`) encodes the page's own URL. A suspended
 * organization's card still shows its balance: that money belongs to the
 * cardholder.
 */
class PublicCardController extends Controller
{
    /** Used when the stored brand color is not a plain hex color. */
    public const DEFAULT_COLOR = '#000000';

    public function show(Request $request, string $token): Response
    {
        $card = $this->findByToken($token);

        if ($card === null) {
            return $this->notFound($request);
        }

        return $this->withPrivateHeaders(Inertia::render('public-card/show', [
            'card' => [
                'balance' => $card->balance,
                'status' => $card->status->value,
                // Whether an email is saved, never the address itself.
                'has_email' => (bool) $card->getAttribute('has_email'),
            ],
            'organization' => [
                'name' => $card->getAttribute('organization_name'),
                'logo_url' => $card->getAttribute('organization_logo_url'),
                'primary_color' => self::brandColor($card->getAttribute('organization_primary_color')),
                'currency' => $card->getAttribute('organization_currency'),
            ],
        ])->toResponse($request));
    }

    /**
     * The card's QR, for the cardholder to show at the counter. It encodes
     * this same page's URL, which the reader accepts. Unknown and malformed
     * tokens get the not-found page, like the card page.
     */
    public function qr(Request $request, string $token, CardQrCode $qr): Response
    {
        $card = self::isWellFormed($token)
            ? Card::query()->select(['qr_token'])->where('qr_token', $token)->first()
            : null;

        if ($card === null) {
            return $this->notFound($request);
        }

        return $this->withPrivateHeaders(response($qr->svg($card), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]));
    }

    /**
     * Save the cardholder's email for later balance updates. v1 stores it and
     * sends nothing. An email already on the card is not replaced from here;
     * the business can change it from the dashboard. A card that is not
     * activated yet takes no email: it is still stock, and whoever handles
     * it before it's sold could claim it.
     */
    public function email(SaveCardEmailRequest $request, string $token): Response
    {
        $card = self::isWellFormed($token)
            ? Card::query()->select(['id', 'email', 'status'])->where('qr_token', $token)->first()
            : null;

        if ($card === null) {
            return $this->notFound($request);
        }

        if ($card->status === CardStatus::Inactive) {
            throw ValidationException::withMessages([
                'email' => __('This card is not activated yet.'),
            ]);
        }

        if ($card->email !== null) {
            throw ValidationException::withMessages([
                'email' => __('An email is already saved for this card.'),
            ]);
        }

        $card->update(['email' => $request->email()]);

        Flash::success(FlashMessage::CardholderEmailSaved);

        return $this->redirectBack($token);
    }

    /**
     * The card with only the fields the public page may show: business name,
     * logo, color and currency, plus the card's balance, status, and whether
     * it has an email.
     */
    private function findByToken(string $token): ?Card
    {
        if (! self::isWellFormed($token)) {
            return null;
        }

        return Card::query()
            ->join('programs', 'programs.id', '=', 'cards.program_id')
            ->join('organizations', 'organizations.id', '=', 'programs.organization_id')
            ->where('cards.qr_token', $token)
            ->first([
                'cards.balance',
                'cards.status',
                DB::raw('cards.email is not null as has_email'),
                'organizations.name as organization_name',
                'organizations.logo_url as organization_logo_url',
                'organizations.primary_color as organization_primary_color',
                'organizations.currency as organization_currency',
            ]);
    }

    /**
     * Unknown and malformed tokens get the same page and status, so the
     * response does not tell whether a token is close to a real one.
     */
    private function notFound(Request $request): Response
    {
        return $this->withPrivateHeaders(
            Inertia::render('public-card/not-found')->toResponse($request)->setStatusCode(404),
        );
    }

    /**
     * The URL holds the token: keep it out of search engines and out of the
     * Referer header sent when loading a logo from another host.
     */
    private function withPrivateHeaders(Response $response): Response
    {
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        return $response;
    }

    private function redirectBack(string $token): RedirectResponse
    {
        return redirect()->route('public-card.show', ['token' => $token]);
    }

    public static function isWellFormed(string $token): bool
    {
        return preg_match(sprintf('/^[A-Za-z0-9_-]{%d}$/', CardCodeGenerator::TOKEN_LENGTH), $token) === 1;
    }

    /**
     * The organization's color as `#rgb` or `#rrggbb`, or black. The value is
     * set as a CSS variable, so anything else is dropped.
     */
    public static function brandColor(mixed $color): string
    {
        return is_string($color) && preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color) === 1
            ? strtolower($color)
            : self::DEFAULT_COLOR;
    }
}
