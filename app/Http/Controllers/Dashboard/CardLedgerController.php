<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Enums\TransactionUnit;
use App\Exceptions\LedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\AdjustBalanceRequest;
use App\Http\Requests\Ledger\AdjustPointsRequest;
use App\Http\Requests\Ledger\EarnPointsRequest;
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
        $before = $card->points;

        return $this->post(
            fn () => $this->ledger->spend($card, $request->amount(), $request->user(), $request->note()),
            $card,
            FlashMessage::CardCharged,
            $request->boolean('reader'),
            // A charge on a program with rewards earns points too: the toast says how many.
            fn () => $card->points > $before ? ['points' => $card->points - $before, 'total' => $card->points] : [],
        );
    }

    /**
     * Award points for a purchase or a visit, by the program's rule.
     */
    public function earn(EarnPointsRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->earnForPurchase($card, $request->amount(), $request->user()),
            $card,
            FlashMessage::PointsEarned,
            $request->boolean('reader'),
        );
    }

    /**
     * Correct a card's points. Owners and managers only.
     */
    public function adjustPoints(AdjustPointsRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->adjustPoints($card, $request->points(), $request->user(), $request->note()),
            $card,
            FlashMessage::PointsAdjusted,
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
     * @param  (Closure(): array<string, int>)|null  $extra  More toast params, read after the action ran.
     */
    private function post(Closure $action, Card $card, FlashMessage $message, bool $toScanner = false, ?Closure $extra = null): RedirectResponse
    {
        try {
            $transaction = $action();
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->translated(),
            ]);
        }

        Flash::success($message, $transaction->unit === TransactionUnit::Points
            ? [
                'code' => $card->code,
                'points' => (int) $transaction->amount,
                'total' => (int) $transaction->balance_after,
            ]
            : [
                'code' => $card->code,
                'amount' => $transaction->amount,
                'balance' => $transaction->balance_after,
                'currency' => $card->organization()->currency,
                ...($extra === null ? [] : $extra()),
            ]);

        return $toScanner ? to_route('scan') : back();
    }
}
