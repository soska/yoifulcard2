<?php

namespace App\Models;

use App\Enums\EarnMode;
use App\Enums\ProgramType;
use Carbon\CarbonImmutable;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $organization_id
 * @property string $name
 * @property ProgramType $type
 * @property bool $is_active
 * @property bool $rewards_enabled
 * @property EarnMode|null $earn_mode
 * @property string|null $earn_rate
 * @property int|null $points_expiry_months
 * @property string|null $terms_url
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['organization_id', 'name', 'type', 'is_active', 'terms_url', 'rewards_enabled', 'earn_mode', 'earn_rate', 'points_expiry_months'])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'prepaid',
        'is_active' => true,
        'rewards_enabled' => false,
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ProgramType::class,
            'is_active' => 'boolean',
            'rewards_enabled' => 'boolean',
            'earn_mode' => EarnMode::class,
            'earn_rate' => 'decimal:2',
            'points_expiry_months' => 'integer',
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
     * @return HasMany<Reward, $this>
     */
    public function rewards(): HasMany
    {
        return $this->hasMany(Reward::class);
    }

    /**
     * @return HasMany<Card, $this>
     */
    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
