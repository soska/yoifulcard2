<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CardStatus;
use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Transaction;
use App\Support\CurrentOrganization;
use App\Support\TransactionFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** How many transactions the home page lists. */
    public const RECENT_LIMIT = 10;

    /**
     * The home page: card counts, outstanding balance, this month's activity,
     * the latest transactions, and plan usage.
     */
    public function __invoke(Request $request): Response
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        $recent = Transaction::query()
            ->forOrganization($organization)
            ->with(['card:id,code', 'performer:id,name'])
            ->latest()
            ->orderByDesc('id')
            ->limit(self::RECENT_LIMIT)
            ->get()
            ->map(fn (Transaction $transaction) => TransactionController::props($transaction));

        return Inertia::render('dashboard', [
            'stats' => self::stats($organization),
            'recentTransactions' => $recent,
            'currency' => $organization->currency,
            'usage' => $organization->cardUsage(),
            'canCreateCards' => Gate::allows('create', [Card::class, $organization]),
        ]);
    }

    /**
     * Totals for the home page, straight from the database. The balance is a
     * decimal string; the month starts at local midnight on the 1st in the
     * organization's timezone.
     *
     * @return array{cards: int, active: int, frozen: int, depleted: int, cancelled: int, outstandingBalance: string, month: string, monthTransactions: int}
     */
    public static function stats(Organization $organization): array
    {
        $totals = Card::query()
            ->forOrganization($organization)
            ->toBase()
            ->selectRaw('count(*) as cards')
            ->selectRaw('count(*) filter (where status = ?) as active', [CardStatus::Active->value])
            ->selectRaw('count(*) filter (where status = ?) as frozen', [CardStatus::Frozen->value])
            ->selectRaw('count(*) filter (where status = ?) as depleted', [CardStatus::Depleted->value])
            ->selectRaw('count(*) filter (where status = ?) as cancelled', [CardStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(balance), 0) as balance')
            ->first();

        $month = now($organization->timezone)->startOfMonth()->toDateString();

        $monthTransactions = Transaction::query()
            ->forOrganization($organization)
            ->where('created_at', '>=', TransactionFilters::dayStart($month, $organization->timezone))
            ->count();

        return [
            'cards' => (int) $totals->cards,
            'active' => (int) $totals->active,
            'frozen' => (int) $totals->frozen,
            'depleted' => (int) $totals->depleted,
            'cancelled' => (int) $totals->cancelled,
            'outstandingBalance' => bcadd((string) $totals->balance, '0', 2),
            'month' => $month,
            'monthTransactions' => $monthTransactions,
        ];
    }
}
