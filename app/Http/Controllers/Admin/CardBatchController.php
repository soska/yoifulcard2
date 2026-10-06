<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\CardBatchController as DashboardCardBatchController;
use App\Http\Requests\CardBatches\StoreCardBatchRequest;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Services\CardBatchIssuer;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Card batches for any business, whether or not it can preissue cards
 * itself. Batches made here are marked issued_by_admin and may carry notes
 * (for example, what we charged for printing). The batches list is on the
 * admin organization page.
 */
class CardBatchController extends Controller
{
    public function __construct(private readonly CardBatchIssuer $issuer) {}

    public function store(StoreCardBatchRequest $request, Organization $organization): RedirectResponse
    {
        $batch = DashboardCardBatchController::attempt(fn () => $this->issuer->issue(
            $organization,
            $request->cardCount(),
            $request->user(),
            byAdmin: true,
            notes: $request->notes(),
        ));

        DashboardCardBatchController::flashCreated($batch);

        return to_route('admin.batches.show', $batch);
    }

    public function show(Request $request, CardBatch $batch): Response
    {
        $organization = $batch->organization;

        $props = DashboardCardBatchController::showProps($batch, $request);

        return Inertia::render('admin/batches/show', [
            ...$props,
            'batch' => [...$props['batch'], 'notes' => $batch->notes],
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'status' => $organization->status->value,
            ],
        ]);
    }

    /**
     * Cancel every card in the batch that is not activated yet.
     */
    public function void(CardBatch $batch): RedirectResponse
    {
        $cancelled = DashboardCardBatchController::attempt(fn () => $this->issuer->void($batch));

        Flash::success(FlashMessage::CardBatchVoided, ['count' => $cancelled]);

        return back();
    }
}
