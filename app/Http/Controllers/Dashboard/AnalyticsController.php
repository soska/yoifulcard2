<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Support\CurrentOrganization;
use App\Support\TransactionFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cards created and transaction volume per day, over the last 7 to 90 days.
 *
 * Days are the organization's local days. Each day starts at local midnight,
 * converted to UTC with TransactionFilters::dayStart, the same boundaries the
 * transactions page filters by. The database puts each row into its day with
 * width_bucket over those boundaries, so the grouping does not depend on the
 * database's own timezone data.
 */
class AnalyticsController extends Controller
{
    /** @var list<int> */
    public const RANGES = [7, 30, 60, 90];

    public const DEFAULT_RANGE = 30;

    public function __invoke(Request $request): Response
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        $days = (int) $request->query('days', (string) self::DEFAULT_RANGE);
        $days = in_array($days, self::RANGES, true) ? $days : self::DEFAULT_RANGE;

        return Inertia::render('analytics/index', [
            ...self::report($organization, $days),
            'ranges' => self::RANGES,
            'currency' => $organization->currency,
        ]);
    }

    /**
     * @return array{days: int, from: string, to: string, series: list<array{date: string, cards: int, load: int, spend: int, adjustment: int, loadAmount: string, spendAmount: string}>, summary: array{cards: int, transactions: int, loadAmount: string, spendAmount: string}}
     */
    public static function report(Organization $organization, int $days): array
    {
        $timezone = $organization->timezone;
        $today = Carbon::now($timezone)->startOfDay();

        // Local dates, oldest first, and the UTC instant each one starts at.
        $dates = [];
        $starts = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date = $today->copy()->subDays($offset)->toDateString();
            $dates[] = $date;
            $starts[] = TransactionFilters::dayStart($date, $timezone)->format('Y-m-d H:i:s');
        }

        $from = $starts[0];
        $until = TransactionFilters::dayStart($today->toDateString(), $timezone, addDays: 1)->format('Y-m-d H:i:s');
        $thresholds = '{'.implode(',', array_map(fn (string $start) => '"'.$start.'"', $starts)).'}';

        $cardsByDay = Card::query()
            ->forOrganization($organization)
            ->toBase()
            ->selectRaw('width_bucket(created_at, ?::timestamp[]) as bucket', [$thresholds])
            ->selectRaw('count(*) as total')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->groupByRaw('1')
            ->pluck('total', 'bucket');

        $transactionRows = Transaction::query()
            ->forOrganization($organization)
            ->toBase()
            ->selectRaw('width_bucket(created_at, ?::timestamp[]) as bucket', [$thresholds])
            ->addSelect('type')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(amount) as amount')
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $until)
            ->groupByRaw('1, 2')
            ->get();

        $series = [];

        foreach ($dates as $index => $date) {
            $series[$index + 1] = [
                'date' => $date,
                'cards' => (int) ($cardsByDay[$index + 1] ?? 0),
                'load' => 0,
                'spend' => 0,
                'adjustment' => 0,
                'loadAmount' => '0.00',
                'spendAmount' => '0.00',
            ];
        }

        $summary = ['cards' => array_sum(array_column($series, 'cards')), 'transactions' => 0, 'loadAmount' => '0.00', 'spendAmount' => '0.00'];

        foreach ($transactionRows as $row) {
            $bucket = (int) $row->bucket;
            $type = TransactionType::tryFrom((string) $row->type);

            if (! isset($series[$bucket]) || $type === null || ! in_array($type->value, TransactionType::visibleValues(), true)) {
                continue;
            }

            $series[$bucket][$type->value] += (int) $row->total;
            $summary['transactions'] += (int) $row->total;

            if ($type === TransactionType::Load || $type === TransactionType::Spend) {
                $key = $type->value.'Amount';
                $series[$bucket][$key] = bcadd($series[$bucket][$key], (string) $row->amount, 2);
                $summary[$key] = bcadd($summary[$key], (string) $row->amount, 2);
            }
        }

        return [
            'days' => $days,
            'from' => $dates[0],
            'to' => $dates[count($dates) - 1],
            'series' => array_values($series),
            'summary' => $summary,
        ];
    }
}
