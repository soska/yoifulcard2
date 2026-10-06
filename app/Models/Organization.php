<?php

namespace App\Models;

use App\Enums\CardStatus;
use App\Enums\OrganizationStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrganizationFactory;
use DateTimeZone;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_url
 * @property string $primary_color
 * @property string $currency
 * @property string $timezone
 * @property OrganizationStatus $status
 * @property int|null $card_limit
 * @property int|null $preissue_limit
 * @property bool $can_preissue
 * @property string|null $plan_notes
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug', 'logo_url', 'primary_color', 'currency', 'timezone', 'status', 'card_limit', 'preissue_limit', 'can_preissue', 'plan_notes'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids;

    public const DEFAULT_TIMEZONE = 'America/Mexico_City';

    /**
     * Currencies the settings form offers. An organization keeps a currency
     * outside this list until someone changes it.
     */
    public const CURRENCIES = ['MXN', 'USD', 'CAD', 'EUR', 'GBP'];

    /** Where uploaded logos go on the public disk. */
    public const LOGO_DIRECTORY = 'logos';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'primary_color' => '#000000',
        'currency' => 'MXN',
        'timezone' => self::DEFAULT_TIMEZONE,
        'status' => 'active',
        'can_preissue' => false,
    ];

    /**
     * Refuse to save a timezone PHP does not know, so every date filter and
     * displayed time can rely on it.
     */
    protected static function booted(): void
    {
        static::saving(function (Organization $organization): void {
            if ($organization->isDirty('timezone') && ! self::isValidTimezone($organization->timezone)) {
                throw new InvalidArgumentException("Unknown timezone [{$organization->timezone}].");
            }
        });
    }

    /**
     * Whether the value is one of PHP's timezone identifiers.
     */
    public static function isValidTimezone(mixed $timezone): bool
    {
        return is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationStatus::class,
            'card_limit' => 'integer',
            'preissue_limit' => 'integer',
            'can_preissue' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Program, $this>
     */
    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    /**
     * @return HasManyThrough<Card, Program, $this>
     */
    public function cards(): HasManyThrough
    {
        return $this->hasManyThrough(Card::class, Program::class);
    }

    /**
     * The program cards are issued from and that settings edit: the oldest
     * active one.
     */
    public function defaultProgram(): ?Program
    {
        return $this->programs()->where('is_active', true)->oldest()->oldest('id')->first();
    }

    /**
     * @return HasMany<CardBatch, $this>
     */
    public function cardBatches(): HasMany
    {
        return $this->hasMany(CardBatch::class);
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Only an active organization can change data. Suspended and cancelled
     * organizations are read-only.
     */
    public function isWritable(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    /**
     * Card usage against the plan's card limit. A null limit is unlimited.
     * Usage at 80% or more is "near" the limit; at 100% creating and
     * activating cards is blocked (CardLimit).
     *
     * Inactive cards (preissued stock) don't count until they are activated;
     * `stock` is how many there are.
     *
     * @return array{used: int, limit: int|null, percent: int|null, nearLimit: bool, atLimit: bool, stock: int}
     */
    public function cardUsage(): array
    {
        $counts = Card::query()
            ->forOrganization($this)
            ->toBase()
            ->selectRaw('count(*) filter (where status <> ?) as used', [CardStatus::Inactive->value])
            ->selectRaw('count(*) filter (where status = ?) as stock', [CardStatus::Inactive->value])
            ->first();

        $used = (int) $counts->used;
        $stock = (int) $counts->stock;
        $limit = $this->card_limit;

        if ($limit === null) {
            return ['used' => $used, 'limit' => null, 'percent' => null, 'nearLimit' => false, 'atLimit' => false, 'stock' => $stock];
        }

        $atLimit = $used >= $limit;
        $percent = $limit > 0 ? (int) floor($used * 100 / $limit) : 100;

        return [
            'used' => $used,
            'limit' => $limit,
            'percent' => $percent,
            'nearLimit' => ! $atLimit && $percent >= 80,
            'atLimit' => $atLimit,
            'stock' => $stock,
        ];
    }

    /**
     * Build a slug from the name that no other organization uses yet.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'business';

        if (! static::query()->where('slug', $base)->exists()) {
            return $base;
        }

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $slug = $base.'-'.Str::lower(Str::random(6));

            if (! static::query()->where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        throw new RuntimeException("Could not generate a unique slug for [{$name}].");
    }
}
