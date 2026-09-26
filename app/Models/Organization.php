<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string|null $logo_url
 * @property string $primary_color
 * @property string $currency
 * @property OrganizationStatus $status
 * @property int|null $card_limit
 * @property string|null $plan_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'slug', 'logo_url', 'primary_color', 'currency', 'status', 'card_limit', 'plan_notes'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'primary_color' => '#000000',
        'currency' => 'MXN',
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
            'status' => OrganizationStatus::class,
            'card_limit' => 'integer',
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
