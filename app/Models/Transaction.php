<?php

namespace App\Models;

use App\Enums\TransactionType;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A ledger entry. Only App\Services\CardLedger writes these.
 *
 * @property string $id
 * @property string $card_id
 * @property TransactionType $type
 * @property string $amount
 * @property string $balance_after
 * @property string|null $note
 * @property int $performed_by
 * @property Carbon|null $created_at
 * @property-read Card $card
 * @property-read User $performer
 */
#[Fillable(['card_id', 'type', 'amount', 'balance_after', 'note', 'performed_by'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUuids;

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
            'type' => TransactionType::class,
            'amount' => 'decimal:2',
            'balance_after' => 'decimal:2',
            'performed_by' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Limit the query to transactions on one organization's cards.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeForOrganization(Builder $query, Organization|string $organization): void
    {
        $query->whereIn('card_id', Card::query()->select('id')->forOrganization($organization));
    }
}
