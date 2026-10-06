<?php

namespace App\Jobs;

use App\Enums\CardBatchPdfAction;
use App\Enums\CardStatus;
use App\Mail\CardBatchPdfReadyMail;
use App\Models\CardBatchPdf;
use App\Models\CardBatchPdfLog;
use App\Services\CardBatchPdfRenderer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use Throwable;

/**
 * Renders a card batch PDF (App\Models\CardBatchPdf) on the queue: a batch
 * can have 1,000 cards, which takes dompdf from seconds to a minute. The
 * file goes to the private disk, the batch remembers the template, the
 * generation is logged, and the person who asked gets an email.
 *
 * Only the cards still in stock when the job runs are printed. A PDF that
 * was replaced or expired while waiting is skipped.
 */
class GenerateCardBatchPdf implements ShouldQueue
{
    use Localizable, Queueable;

    /** Rendering is deterministic: a failure would fail again. */
    public int $tries = 1;

    public int $timeout = 600;

    /** dompdf keeps the whole document in memory: about 260 MB for 1,000 print shop pages. */
    public const MEMORY_LIMIT = '512M';

    public function __construct(public readonly string $pdfId) {}

    public function handle(CardBatchPdfRenderer $renderer): void
    {
        $pdf = CardBatchPdf::find($this->pdfId);

        if ($pdf === null || $pdf->status() !== 'pending' || $pdf->isExpired()) {
            return;
        }

        $batch = $pdf->batch;
        $cards = $batch->cards()
            ->where('status', CardStatus::Inactive)
            ->orderBy('code')
            ->orderBy('id')
            ->get(['id', 'code', 'qr_token']);

        if ($cards->isEmpty()) {
            $pdf->update(['failed_at' => now()]);

            return;
        }

        ini_set('memory_limit', self::MEMORY_LIMIT);

        $result = $this->withLocale($pdf->locale, fn () => $renderer->render($batch, $pdf->template, $cards));

        $disk = Storage::disk(CardBatchPdf::DISK);
        $path = $pdf->filePath();
        $disk->put($path, $result['pdf']);

        $ready = DB::transaction(function () use ($pdf, $batch, $path, $cards, $result): ?CardBatchPdf {
            $pdf = CardBatchPdf::query()->lockForUpdate()->find($pdf->id);

            // Replaced by a newer request while rendering.
            if ($pdf === null) {
                return null;
            }

            $pdf->update([
                'path' => $path,
                'cards' => $cards->count(),
                'pages' => $result['pages'],
                'size' => strlen($result['pdf']),
                'ready_at' => now(),
                'expires_at' => now()->addHours(CardBatchPdf::EXPIRES_AFTER_HOURS),
            ]);

            $batch->update(['template' => $pdf->template->value]);

            CardBatchPdfLog::record($pdf, CardBatchPdfAction::Generated, $pdf->requested_by);

            return $pdf;
        });

        if ($ready === null) {
            $disk->delete($path);

            return;
        }

        // In the requester's chosen language, else the one they asked in.
        Mail::to($ready->requester)
            ->locale($ready->requester->preferredLocale() ?? $ready->locale)
            ->send(new CardBatchPdfReadyMail($ready));
    }

    public function failed(?Throwable $exception): void
    {
        $pdf = CardBatchPdf::find($this->pdfId);

        if ($pdf !== null && $pdf->ready_at === null) {
            $pdf->deleteFile();
            $pdf->update(['failed_at' => now()]);
        }
    }
}
