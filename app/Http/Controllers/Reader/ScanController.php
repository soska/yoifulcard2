<?php

namespace App\Http\Controllers\Reader;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Organization;
use App\Support\CurrentOrganization;
use App\Support\QrPayload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reader: scan a card QR, then charge or add funds. Charging and adding
 * funds post to the Phase 3 ledger routes (CardLedgerController).
 */
class ScanController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('reader/scan');
    }

    /**
     * Find the card for a scanned payload in the current organization. An
     * unknown token, another organization's token, and a malformed payload
     * all get the same "Card not found." error.
     */
    public function lookup(Request $request): RedirectResponse
    {
        $organization = $this->organization($request);
        $token = QrPayload::token($request->input('payload'));

        $card = $token === null ? null : Card::query()
            ->forOrganization($organization)
            ->where('qr_token', $token)
            ->first();

        if ($card === null) {
            throw ValidationException::withMessages([
                'payload' => __('Card not found.'),
            ])->redirectTo(route('scan'));
        }

        return to_route('scan.cards.show', $card);
    }

    /**
     * The card as the reader sees it. Only cards of the current organization
     * open here; any other card is a 404.
     */
    public function show(Request $request, Card $card): Response
    {
        $organization = $this->organization($request);

        $card->loadMissing('program');

        abort_unless($card->program->organization_id === $organization->id, 404);

        Gate::authorize('view', $card);

        return Inertia::render('reader/card', [
            'card' => [
                'id' => $card->id,
                'code' => $card->code,
                'balance' => $card->balance,
                'status' => $card->status->value,
            ],
            'currency' => $organization->currency,
        ]);
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
