<?php

namespace App\Models;

use App\Support\Locales;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property CarbonImmutable|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property CarbonImmutable|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements HasLocalePreference, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<Membership, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /**
     * @return BelongsToMany<Organization, $this>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * @return HasOne<Superadmin, $this>
     */
    public function superadmin(): HasOne
    {
        return $this->hasOne(Superadmin::class);
    }

    public function isSuperadmin(): bool
    {
        return $this->superadmin()->exists();
    }

    /**
     * The user's first membership, which decides the default organization.
     */
    public function firstMembership(): ?Membership
    {
        return $this->memberships()->oldest()->oldest('id')->first();
    }

    public function membershipFor(Organization|string $organization): ?Membership
    {
        $id = $organization instanceof Organization ? $organization->id : $organization;

        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->memberships()->where('organization_id', $id)->first();
    }

    /**
     * The language this person chose, or null when they never chose (then the
     * browser decides; see App\Support\Locales::resolve).
     *
     * HasLocalePreference is the interface Laravel's mail and notification
     * layers check, so a queued email (Email release) is written in the
     * recipient's language, not the language of the request that sent it.
     *
     * The stored value is re-checked against App\Support\Locales: a language
     * dropped from the list later must not reach App::setLocale().
     */
    public function preferredLocale(): ?string
    {
        $stored = $this->attributes['locale'] ?? null;

        return Locales::isSupported($stored) ? $stored : null;
    }
}
