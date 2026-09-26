<?php

namespace App\Support;

use App\Enums\TransactionType;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * The transactions page filters, read from the query string. The list and
 * the CSV export build their query here, so they always agree.
 *
 * Invalid values are dropped rather than rejected, like the cards list.
 * Dates are whole days in the organization's timezone; both ends are
 * inclusive.
 */
final readonly class TransactionFilters
{
    public function __construct(
        public ?TransactionType $type = null,
        public ?string $from = null,
        public ?string $to = null,
        public string $card = '',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $type = TransactionType::tryFrom((string) $request->query('type', ''));

        return new self(
            type: $type !== null && in_array($type->value, TransactionType::visibleValues(), true) ? $type : null,
            from: self::date($request->query('from')),
            to: self::date($request->query('to')),
            card: mb_substr(trim((string) $request->query('card', '')), 0, 50),
        );
    }

    /**
     * The organization's transactions that match, newest first.
     *
     * @return Builder<Transaction>
     */
    public function query(Organization $organization): Builder
    {
        $timezone = $organization->timezone;

        return Transaction::query()
            ->forOrganization($organization)
            ->when($this->type, fn (Builder $query, TransactionType $type) => $query->where('type', $type))
            ->when($this->from, fn (Builder $query, string $from) => $query
                ->where('created_at', '>=', self::dayStart($from, $timezone)))
            ->when($this->to, fn (Builder $query, string $to) => $query
                ->where('created_at', '<', self::dayStart($to, $timezone, addDays: 1)))
            ->when($this->card !== '', function (Builder $query): void {
                $pattern = '%'.addcslashes(mb_strtolower($this->card), '\\%_').'%';

                $query->whereIn('card_id', Card::query()->select('id')->whereRaw('lower(code) like ?', [$pattern]));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /**
     * The filters as query-string values, without empty ones.
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'type' => $this->type?->value,
            'from' => $this->from,
            'to' => $this->to,
            'card' => $this->card,
        ], fn (?string $value) => $value !== null && $value !== '');
    }

    /**
     * @return array{type: string|null, from: string|null, to: string|null, card: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type?->value,
            'from' => $this->from,
            'to' => $this->to,
            'card' => $this->card,
        ];
    }

    /**
     * Local midnight of a day (plus some days) in the organization's
     * timezone, converted to the app timezone the database stores. Days are
     * added before converting, so a DST change still lands on midnight.
     */
    private static function dayStart(string $date, string $timezone, int $addDays = 0): Carbon
    {
        return Carbon::parse($date, $timezone)
            ->addDays($addDays)
            ->startOfDay()
            ->setTimezone(config('app.timezone'));
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year) ? $value : null;
    }
}
