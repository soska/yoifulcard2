<?php

namespace App\Services;

use App\Enums\CardBatchPdfAction;
use App\Enums\CardStatus;
use App\Enums\CardTemplate;
use App\Enums\TemplateAudience;
use App\Exceptions\CardBatchException;
use App\Jobs\GenerateCardBatchPdf;
use App\Models\CardBatch;
use App\Models\CardBatchPdf;
use App\Models\CardBatchPdfLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Asks for printable PDFs of a card batch and hands them out, once.
 *
 * A batch PDF holds the token of every card it prints, and anyone holding a
 * printed card can spend it once it is activated. So a PDF is made on
 * demand by a queued job (App\Jobs\GenerateCardBatchPdf), only for the
 * person who asked, kept on the private disk until it is downloaded or
 * expires, and every one made and every download is logged
 * (App\Models\CardBatchPdfLog).
 *
 * Only cards still in stock are printed: a card that was activated or
 * voided is never printed again.
 */
class CardBatchPrinter
{
    /**
     * Queue a PDF of the batch's cards in stock, for `$user`. It replaces
     * any PDF of this batch the user asked for earlier from the same area.
     *
     * @throws CardBatchException
     */
    public function request(CardBatch $batch, CardTemplate $template, User $user, TemplateAudience $audience): CardBatchPdf
    {
        if (! $template->availableTo($audience)) {
            throw CardBatchException::templateNotAvailable();
        }

        if ($batch->isVoided()) {
            throw CardBatchException::batchVoided();
        }

        if (! $batch->cards()->where('status', CardStatus::Inactive)->exists()) {
            throw CardBatchException::nothingToPrint();
        }

        $byAdmin = $audience === TemplateAudience::Admin;

        return DB::transaction(function () use ($batch, $template, $user, $byAdmin): CardBatchPdf {
            // Deleted one by one so each file goes too (CardBatchPdf::booted).
            self::own($batch, $user, $byAdmin)->get()->each->delete();

            $pdf = $batch->pdfs()->create([
                'template' => $template,
                'requested_by' => $user->id,
                'by_admin' => $byAdmin,
                'locale' => app()->getLocale(),
                'expires_at' => now()->addHours(CardBatchPdf::EXPIRES_AFTER_HOURS),
            ]);

            GenerateCardBatchPdf::dispatch($pdf->id)->afterCommit();

            return $pdf;
        });
    }

    /**
     * The PDF of this batch `$user` asked for from the admin area
     * (`$byAdmin`) or the business's, if it has not expired.
     */
    public function latest(CardBatch $batch, User $user, bool $byAdmin): ?CardBatchPdf
    {
        return self::own($batch, $user, $byAdmin)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * Stream the PDF to `$user` and delete it. Only the person who asked
     * for it can download it, once; a second download finds nothing. Null
     * when there is nothing to download.
     */
    public function download(CardBatchPdf $pdf, User $user): ?StreamedResponse
    {
        return DB::transaction(function () use ($pdf, $user): ?StreamedResponse {
            $pdf = CardBatchPdf::query()->lockForUpdate()->find($pdf->id);

            if ($pdf === null
                || $pdf->requested_by !== $user->id
                || $pdf->status() !== 'ready'
                || $pdf->isExpired()) {
                return null;
            }

            $stream = Storage::disk(CardBatchPdf::DISK)->readStream((string) $pdf->path);

            if (! is_resource($stream)) {
                $pdf->delete();

                return null;
            }

            $name = $pdf->downloadName();

            CardBatchPdfLog::record($pdf, CardBatchPdfAction::Downloaded, $user->id);

            // The open stream still reads the file after this deletes it.
            $pdf->delete();

            return response()->streamDownload(function () use ($stream): void {
                try {
                    fpassthru($stream);
                } finally {
                    fclose($stream);
                }
            }, $name, [
                'Content-Type' => 'application/pdf',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        });
    }

    /**
     * @return HasMany<CardBatchPdf, CardBatch>
     */
    private static function own(CardBatch $batch, User $user, bool $byAdmin): HasMany
    {
        return $batch->pdfs()->where('requested_by', $user->id)->where('by_admin', $byAdmin);
    }
}
