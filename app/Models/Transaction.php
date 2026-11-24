<?php

namespace App\Models;

use App\Enums\TransactionType;
use App\Enums\TransactionUnit;
use Carbon\CarbonImmutable;
use Database\Factories\TransactionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A ledger entry. Only App\Services\CardLedger writes these. The unit says
 * what it counts: money, or reward points (whole numbers kept in amount and
 * balance_after).
 *
 * @property string $id
 * @property string $card_id
 * @property TransactionType $type
 * @property TransactionUnit $unit
 * @property string $amount
 * @property string $balance_after
 * @property string|null $note
 * @property int $performed_by
 * @property string|null $reward_id
 * @property string|null $reward_name
 * @property CarbonImmutable|null $created_at
 * @property-read Card $card
 * @property-read User $performer
 */
#[Fillable(['card_id', 'type', 'unit', 'amount', 'balance_after', 'note', 'performed_by', 'reward_id', 'reward_name'])]
class Transaction extends Model
{
    /** @use HasFactory<TransactionFactory> */
    use HasFactory, HasUuids;

    /**
     * Rows are never updated.
     */
    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'unit' => 'money',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => TransactionType::class,
            'unit' => TransactionUnit::class,
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
     * Limit the query to money entries. The organization-wide reports (the
     * transactions page and export, analytics, the dashboard) only count
     * money until points get their own reporting.
     *
     * @param  Builder<Transaction>  $query
     */
    public function scopeMoney(Builder $query): void
    {
        $query->where('unit', TransactionUnit::Money->value);
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
