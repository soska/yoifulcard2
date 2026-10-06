<?php

namespace App\Enums;

/**
 * What happened to a batch PDF, as the audit log (card_batch_pdf_logs)
 * records it. A batch PDF holds the token of every card it prints, so each
 * one made and each download is logged.
 */
enum CardBatchPdfAction: string
{
    /** The queued job wrote the PDF; the user is who asked for it. */
    case Generated = 'generated';

    /** The PDF was downloaded (and deleted); the user is who downloaded it. */
    case Downloaded = 'downloaded';
}
