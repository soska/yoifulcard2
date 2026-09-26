<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\UpdateCardEmailRequest;
use App\Models\Card;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;

class CardEmailController extends Controller
{
    /**
     * Set or clear the cardholder email.
     */
    public function __invoke(UpdateCardEmailRequest $request, Card $card): RedirectResponse
    {
        $card->update(['email' => $request->validated('email')]);

        Flash::success(FlashMessage::CardEmailSaved);

        return back();
    }
}
