<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\CardBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Preissued (inactive) cards made at once, for printing. Only
 * App\Services\CardBatchIssuer creates and voids these.
 *
 * @property string $id
 * @property string $organization_id
 * @property string $program_id
 * @property int $count
 * @property string|null $template
 * @property int $created_by
 * @property bool $issued_by_admin
 * @property string|null $notes
 * @property CarbonImmutable|null $voided_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Organization $organization
 * @property-read Program $program
 * @property-read User $creator
 * @property-read Collection<int, Card> $cards
 */
#[Fillable(['organization_id', 'program_id', 'count', 'template', 'created_by', 'issued_by_admin', 'notes', 'voided_at'])]
class CardBatch extends Model
{
    /** @use HasFactory<CardBatchFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'count' => 'integer',
            'created_by' => 'integer',
            'issued_by_admin' => 'boolean',
            'voided_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Program, $this>
     */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'batch_id');
    }

    public function isVoided(): bool
    {
        return $this->voided_at !== null;
    }
}
