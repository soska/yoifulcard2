<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Exceptions\LedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\AdjustBalanceRequest;
use App\Http\Requests\Ledger\LedgerAmountRequest;
use App\Models\Card;
use App\Models\Transaction;
use App\Services\CardLedger;
use App\Support\Flash;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Add funds, charge, adjust, and activate, from the card page and (all but
 * adjust) from the reader. The form requests authorize the member
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
     * Activate a preissued card with an amount. Refused at the card limit.
     */
    public function activate(LedgerAmountRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->activate($card, $request->amount(), $request->user()),
            $card,
            FlashMessage::CardActivated,
            $request->boolean('reader'),
        );
    }

    /**
     * On success, go back to the page that posted, or to the scanner when the
     * reader posted (`reader=1`), so it is ready for the next card. Errors
     * always go back to the page that posted.
     *
     * The toast carries the amount and the new balance: on the reader it is
     * the only place staff see what is left before the next scan.
     *
     * @param  Closure(): Transaction  $action
     */
    private function post(Closure $action, Card $card, FlashMessage $message, bool $toScanner = false): RedirectResponse
    {
        try {
            $transaction = $action();
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->translated(),
            ]);
        }

        Flash::success($message, [
            'code' => $card->code,
            'amount' => $transaction->amount,
            'balance' => $transaction->balance_after,
            'currency' => $card->organization()->currency,
        ]);

        return $toScanner ? to_route('scan') : back();
    }
}
