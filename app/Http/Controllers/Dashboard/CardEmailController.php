<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\UpdateCardEmailRequest;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CardEmailController extends Controller
{
    /**
     * Set or clear the cardholder email.
     */
    public function __invoke(UpdateCardEmailRequest $request, Card $card): RedirectResponse
    {
        $card->update(['email' => $request->validated('email')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cardholder email saved.')]);

        return back();
    }
}
