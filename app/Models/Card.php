<?php

namespace App\Models;

use App\Enums\CardStatus;
use Carbon\CarbonImmutable;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $program_id
 * @property string|null $batch_id
 * @property string $code
 * @property string $qr_token
 * @property string $balance
 * @property CardStatus $status
 * @property string|null $email
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $activated_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Program $program
 * @property-read CardBatch|null $batch
 * @property-read Collection<int, Transaction> $transactions
 */
#[Fillable(['program_id', 'batch_id', 'code', 'qr_token', 'balance', 'status', 'email', 'last_used_at', 'activated_at'])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory, HasUuids;

    /**
     * The token is only used on the server to render the QR image. It must
     * never reach page props or JSON.
     *
     * @var list<string>
     */
    protected $hidden = ['qr_token'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'balance' => '0.00',
        'status' => 'active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'status' => CardStatus::class,
            'last_used_at' => 'datetime',
            'activated_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * The batch a preissued card came from; null for cards created one at a
     * time.
     *
     * @return BelongsTo<CardBatch, $this>
     */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(CardBatch::class);
    }

    /**
     * @return HasMany<Transaction, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * The organization that owns the card, through its program.
     */
    public function organization(): Organization
    {
        return $this->program->organization;
    }

    /**
     * Limit the query to cards of one organization.
     *
     * @param  Builder<Card>  $query
     */
    public function scopeForOrganization(Builder $query, Organization|string $organization): void
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        $query->whereIn('program_id', Program::query()->select('id')->where('organization_id', $id));
    }

    /**
     * SQL condition for cards that take a slot under the card limit. Stock
     * (inactive) doesn't, and neither does voided stock: preissued cards
     * cancelled before they were ever activated. Cards created one at a time
     * are active with no activated_at, so batch_id tells voided stock apart.
     */
    public static function countsTowardLimitSql(): string
    {
        $inactive = CardStatus::Inactive->value;
        $cancelled = CardStatus::Cancelled->value;

        return "cards.status <> '{$inactive}' and not (cards.status = '{$cancelled}' and cards.activated_at is null and cards.batch_id is not null)";
    }

    /**
     * Limit the query to cards that take a slot under the card limit (see
     * countsTowardLimitSql()).
     *
     * @param  Builder<Card>  $query
     */
    public function scopeCountingTowardLimit(Builder $query): void
    {
        $query->whereRaw(self::countsTowardLimitSql());
    }

    /**
     * The URL encoded in the card's QR code.
     */
    public function qrPayload(): string
    {
        return rtrim((string) config('app.url'), '/').'/c/'.$this->qr_token;
    }
}
