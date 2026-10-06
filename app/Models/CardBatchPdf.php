<?php

namespace App\Models;

use App\Enums\CardTemplate;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * A printable PDF of a card batch, for the one person who asked for it.
 * App\Jobs\GenerateCardBatchPdf writes the file on the private disk; it is
 * deleted with the row when downloaded (App\Services\CardBatchPrinter) or
 * once it expires (`model:prune`, scheduled hourly). The file holds the
 * token of every card it prints, so it is never kept.
 *
 * @property string $id
 * @property string $card_batch_id
 * @property CardTemplate $template
 * @property int $requested_by
 * @property bool $by_admin
 * @property string $locale
 * @property string|null $path
 * @property int|null $cards
 * @property int|null $pages
 * @property int|null $size
 * @property CarbonImmutable|null $ready_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read CardBatch $batch
 * @property-read User $requester
 */
#[Fillable(['card_batch_id', 'template', 'requested_by', 'by_admin', 'locale', 'path', 'cards', 'pages', 'size', 'ready_at', 'failed_at', 'expires_at'])]
class CardBatchPdf extends Model
{
    use HasUuids, Prunable;

    /** The private disk the files are written to. */
    public const DISK = 'local';

    /** Where on the disk. */
    public const DIRECTORY = 'card-batch-pdfs';

    /** How long a PDF waits to be downloaded, from when it is asked for and again from when it is ready. */
    public const EXPIRES_AFTER_HOURS = 24;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => CardTemplate::class,
            'requested_by' => 'integer',
            'by_admin' => 'boolean',
            'cards' => 'integer',
            'pages' => 'integer',
            'size' => 'integer',
            'ready_at' => 'datetime',
            'failed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Delete the file along with the row, whoever deletes it.
     */
    protected static function booted(): void
    {
        static::deleted(function (CardBatchPdf $pdf): void {
            $pdf->deleteFile();
        });
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Expired PDFs, ready or not: `model:prune` deletes them and their files.
     *
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('expires_at', '<=', now());
    }

    /**
     * Where the file goes on the disk.
     */
    public function filePath(): string
    {
        return self::DIRECTORY.'/'.$this->card_batch_id.'/'.$this->id.'.pdf';
    }

    /**
     * `pending` while the job runs, then `ready` or `failed`.
     *
     * @return 'pending'|'ready'|'failed'
     */
    public function status(): string
    {
        return match (true) {
            $this->failed_at !== null => 'failed',
            $this->ready_at !== null && $this->path !== null => 'ready',
            default => 'pending',
        };
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Delete the file, even one the job wrote but never recorded in `path`
     * (it stopped between writing the file and saving the row).
     */
    public function deleteFile(): void
    {
        Storage::disk(self::DISK)->delete($this->path ?? $this->filePath());
    }

    /**
     * The download's file name: the business's name, the batch date and the
     * template, never anything from a card.
     */
    public function downloadName(): string
    {
        $batch = $this->batch;
        $slug = $batch->organization->slug;

        return $slug.'-'.$batch->created_at?->format('Y-m-d').'-'.str_replace('_', '-', $this->template->value).'.pdf';
    }
}
