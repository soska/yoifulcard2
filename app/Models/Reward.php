<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a card's points buy. One reward is a punch card ("free coffee at
 * 10 points"); several are tiers.
 *
 * @property string $id
 * @property string $program_id
 * @property string $name
 * @property int $points_cost
 * @property bool $is_active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Program $program
 */
#[Fillable(['program_id', 'name', 'points_cost', 'is_active'])]
class Reward extends Model
{
    /** @use HasFactory<RewardFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points_cost' => 'integer',
            'is_active' => 'boolean',
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
     * Limit the query to rewards that can be redeemed now.
     *
     * @param  Builder<Reward>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * The cheapest first, then by name, which is the order customers see.
     *
     * @param  Builder<Reward>  $query
     */
    public function scopeByCost(Builder $query): void
    {
        $query->orderBy('points_cost')->orderBy('name')->orderBy('id');
    }
}
