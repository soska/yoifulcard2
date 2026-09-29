<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\SuperadminFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property CarbonImmutable $granted_at
 * @property int|null $granted_by
 */
#[Fillable(['user_id', 'granted_at', 'granted_by'])]
class Superadmin extends Model
{
    /** @use HasFactory<SuperadminFactory> */
    use HasFactory, HasUuids;

    /**
     * Superadmins only record when the role was granted.
     *
     * @var bool
     */
    public $timestamps = false;

    protected static function booted(): void
    {
        static::creating(function (Superadmin $superadmin): void {
            $superadmin->granted_at ??= now();
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }
}
