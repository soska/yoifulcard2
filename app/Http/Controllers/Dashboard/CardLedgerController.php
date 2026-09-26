<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Exceptions\LedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\AdjustBalanceRequest;
use App\Http\Requests\Ledger\LedgerAmountRequest;
use App\Models\Card;
use App\Services\CardLedger;
use App\Support\Flash;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Add funds, charge, and adjust, from the card page and (load and spend
 * only) from the reader. The form requests authorize the member
 * (CardPolicy::transact). Every balance change goes through
 * CardLedger; its refusals come back as validation errors.
 */
class CardLedgerController extends Controller
{
    public function __construct(private readonly CardLedger $ledger) {}

    public function load(LedgerAmountRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->load($card, $request->amount(), $request->user(), $request->note()),
            $card,
            FlashMessage::FundsAdded,
            $request->boolean('reader'),
        );
    }

    public function spend(LedgerAmountRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->spend($card, $request->amount(), $request->user(), $request->note()),
            $card,
            FlashMessage::CardCharged,
            $request->boolean('reader'),
        );
    }

    public function adjust(AdjustBalanceRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->adjust($card, $request->amount(), $request->user(), $request->note()),
            $card,
            FlashMessage::BalanceAdjusted,
        );
    }

    /**
     * On success, go back to the page that posted, or to the scanner when the
     * reader posted (`reader=1`), so it is ready for the next card. Errors
     * always go back to the page that posted.
     */
    private function post(Closure $action, Card $card, FlashMessage $message, bool $toScanner = false): RedirectResponse
    {
        try {
            $action();
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->translated(),
            ]);
        }

        Flash::success($message, ['code' => $card->code]);

        return $toScanner ? to_route('scan') : back();
    }
}
