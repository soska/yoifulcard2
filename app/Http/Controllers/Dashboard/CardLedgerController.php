<?php

namespace App\Http\Controllers\Dashboard;

use App\Exceptions\LedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ledger\AdjustBalanceRequest;
use App\Http\Requests\Ledger\LedgerAmountRequest;
use App\Models\Card;
use App\Services\CardLedger;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Add funds, charge, and adjust. The form requests authorize the member
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
            __('Funds added to :code.', ['code' => $card->code]),
        );
    }

    public function spend(LedgerAmountRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->spend($card, $request->amount(), $request->user(), $request->note()),
            __('Charged :code.', ['code' => $card->code]),
        );
    }

    public function adjust(AdjustBalanceRequest $request, Card $card): RedirectResponse
    {
        return $this->post(
            fn () => $this->ledger->adjust($card, $request->amount(), $request->user(), $request->note()),
            __('Balance of :code adjusted.', ['code' => $card->code]),
        );
    }

    private function post(Closure $action, string $message): RedirectResponse
    {
        try {
            $action();
        } catch (LedgerException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->translated(),
            ]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}
