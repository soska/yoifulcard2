<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CardTemplate;
use App\Enums\FlashMessage;
use App\Enums\TemplateAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardBatches\PrintCardBatchRequest;
use App\Models\CardBatch;
use App\Models\CardBatchPdfLog;
use App\Models\User;
use App\Services\CardBatchPrinter;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Batch PDFs for a business that can preissue cards: owners and managers
 * print with the business templates and download what they asked for.
 * Superadmins print for any business from Admin\CardBatchPdfController.
 * A PDF asked for in one area can't be downloaded from the other.
 */
class CardBatchPdfController extends Controller
{
    /** How many audit log entries the batch page shows. */
    public const LOG_LIMIT = 20;

    public function __construct(private readonly CardBatchPrinter $printer) {}

    /**
     * Queue a PDF of the batch's cards in stock.
     */
    public function store(PrintCardBatchRequest $request, CardBatch $batch): RedirectResponse
    {
        Gate::authorize('preissue', $batch->organization);

        CardBatchController::attempt(fn () => $this->printer->request($batch, $request->template(), $request->user(), TemplateAudience::Business));

        Flash::success(FlashMessage::CardBatchPdfRequested);

        return back();
    }

    /**
     * Download the PDF, once. It is deleted as it is sent.
     */
    public function download(Request $request, CardBatch $batch, string $pdf): Response
    {
        Gate::authorize('viewBatches', $batch->organization);

        return self::downloadOrExplain($this->printer, $request, $batch, $pdf, byAdmin: false, back: route('batches.show', $batch));
    }

    /**
     * Stream the PDF when it belongs to this batch and area, is ready, and
     * the user asked for it; otherwise go back to the batch page with an
     * error.
     */
    public static function downloadOrExplain(CardBatchPrinter $printer, Request $request, CardBatch $batch, string $pdfId, bool $byAdmin, string $back): Response
    {
        // Not route model binding: a PDF already downloaded or pruned gets
        // the explanation, not a 404.
        $pdf = $batch->pdfs()->where('by_admin', $byAdmin)->find($pdfId);

        $response = $pdf !== null
            ? $printer->download($pdf, $request->user())
            : null;

        return $response ?? redirect($back)->withErrors([
            'batch' => __('This PDF is no longer available. Make a new one.'),
        ]);
    }

    /**
     * The print form and the user's current PDF for the batch page, plus the
     * batch's PDF log. On the business page, entries by Yoiful staff don't
     * name the person.
     *
     * @return array{print: array<string, mixed>}
     */
    public static function props(CardBatch $batch, User $user, TemplateAudience $audience): array
    {
        $byAdmin = $audience === TemplateAudience::Admin;
        $pdf = app(CardBatchPrinter::class)->latest($batch, $user, $byAdmin);

        $logs = $batch->pdfLogs()
            ->with('user:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LOG_LIMIT)
            ->get()
            ->map(fn (CardBatchPdfLog $log) => [
                'id' => $log->id,
                'action' => $log->action->value,
                'template' => $log->template->value,
                'cards' => $log->cards,
                'by_admin' => $log->by_admin,
                'user' => $log->by_admin && ! $byAdmin ? null : $log->user->name,
                'created_at' => $log->created_at?->toIso8601String(),
            ])
            ->all();

        return [
            'print' => [
                'templates' => array_map(fn (CardTemplate $template) => $template->value, CardTemplate::for($audience)),
                'pdf' => $pdf === null ? null : [
                    'id' => $pdf->id,
                    'template' => $pdf->template->value,
                    'status' => $pdf->status(),
                    'cards' => $pdf->cards,
                    'pages' => $pdf->pages,
                    'size' => $pdf->size,
                    'created_at' => $pdf->created_at?->toIso8601String(),
                    'expires_at' => $pdf->expires_at->toIso8601String(),
                ],
                'logs' => $logs,
            ],
        ];
    }
}
