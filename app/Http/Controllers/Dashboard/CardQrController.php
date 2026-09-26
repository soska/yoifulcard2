<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Services\CardQrCode;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CardQrController extends Controller
{
    /**
     * The QR image, inline, for the card page.
     */
    public function svg(Card $card, CardQrCode $qr): Response
    {
        Gate::authorize('view', $card);

        return response($qr->svg($card), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * The QR image as a PNG download named after the card code.
     */
    public function png(Card $card, CardQrCode $qr): Response
    {
        Gate::authorize('view', $card);

        return response($qr->png($card), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$card->code.'.png"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
