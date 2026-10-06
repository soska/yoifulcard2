<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FlashMessage;
use App\Enums\TemplateAudience;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Dashboard\CardBatchController as DashboardCardBatchController;
use App\Http\Controllers\Dashboard\CardBatchPdfController as DashboardCardBatchPdfController;
use App\Http\Requests\CardBatches\PrintCardBatchRequest;
use App\Models\CardBatch;
use App\Services\CardBatchPrinter;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batch PDFs for any business, with every template, including the ones
 * only Yoiful prints with (print shop). For now we print on behalf of
 * businesses, so this is where most PDFs are made.
 */
class CardBatchPdfController extends Controller
{
    public function __construct(private readonly CardBatchPrinter $printer) {}

    /**
     * Queue a PDF of the batch's cards in stock.
     */
    public function store(PrintCardBatchRequest $request, CardBatch $batch): RedirectResponse
    {
        DashboardCardBatchController::attempt(fn () => $this->printer->request($batch, $request->template(), $request->user(), TemplateAudience::Admin));

        Flash::success(FlashMessage::CardBatchPdfRequested);

        return back();
    }

    /**
     * Download the PDF, once. It is deleted as it is sent.
     */
    public function download(Request $request, CardBatch $batch, string $pdf): Response
    {
        return DashboardCardBatchPdfController::downloadOrExplain($this->printer, $request, $batch, $pdf, byAdmin: true, back: route('admin.batches.show', $batch));
    }
}
