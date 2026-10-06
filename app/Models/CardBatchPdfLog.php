<?php

namespace App\Models;

use App\Enums\CardBatchPdfAction;
use App\Enums\CardTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit log of batch PDFs: who made or downloaded one, when, of which batch.
 * Rows outlive the PDF they describe. Only App\Jobs\GenerateCardBatchPdf
 * and App\Services\CardBatchPrinter write these.
 *
 * @property int $id
 * @property string $card_batch_id
 * @property int $user_id
 * @property CardBatchPdfAction $action
 * @property CardTemplate $template
 * @property int $cards
 * @property bool $by_admin
 * @property CarbonImmutable|null $created_at
 * @property-read CardBatch $batch
 * @property-read User $user
 */
#[Fillable(['card_batch_id', 'user_id', 'action', 'template', 'cards', 'by_admin'])]
class CardBatchPdfLog extends Model
{
    /**
     * Rows are never updated.
     */
    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'action' => CardBatchPdfAction::class,
            'template' => CardTemplate::class,
            'cards' => 'integer',
            'by_admin' => 'boolean',
        ];
    }

    /**
     * Write one entry for `$pdf`, by `$user`.
     */
    public static function record(CardBatchPdf $pdf, CardBatchPdfAction $action, int $userId): self
    {
        return self::create([
            'card_batch_id' => $pdf->card_batch_id,
            'user_id' => $userId,
            'action' => $action,
            'template' => $pdf->template,
            'cards' => $pdf->cards ?? 0,
            'by_admin' => $pdf->by_admin,
        ]);
    }

    /**
     * @return BelongsTo<CardBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CardBatch::class, 'card_batch_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
