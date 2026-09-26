<?php

namespace App\Models;

use App\Enums\CardStatus;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $program_id
 * @property string $code
 * @property string $qr_token
 * @property string $balance
 * @property CardStatus $status
 * @property string|null $email
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Program $program
 */
#[Fillable(['program_id', 'code', 'qr_token', 'balance', 'status', 'email', 'last_used_at'])]
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
     * The URL encoded in the card's QR code.
     */
    public function qrPayload(): string
    {
        return rtrim((string) config('app.url'), '/').'/c/'.$this->qr_token;
    }
}
