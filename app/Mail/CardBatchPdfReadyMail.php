<?php

namespace App\Mail;

use App\Models\CardBatchPdf;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Tells whoever asked for a batch PDF that it is ready. It links to the
 * batch page, where the PDF is downloaded; the file itself is never
 * attached. Sent by App\Jobs\GenerateCardBatchPdf, already on the queue, in
 * the language of the request.
 */
class CardBatchPdfReadyMail extends Mailable
{
    public function __construct(public CardBatchPdf $pdf) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('Your card batch PDF is ready'),
        );
    }

    public function content(): Content
    {
        $batch = $this->pdf->batch;

        return new Content(
            markdown: 'mail.card-batch-pdf-ready',
            with: [
                'business' => $batch->organization->name,
                'hours' => CardBatchPdf::EXPIRES_AFTER_HOURS,
                'url' => $this->pdf->by_admin ? route('admin.batches.show', $batch) : route('batches.show', $batch),
            ],
        );
    }
}
