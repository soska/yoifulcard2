<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\Transaction;
use App\Support\CurrentOrganization;
use App\Support\TransactionFilters;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TransactionController extends Controller
{
    public const PER_PAGE = 25;

    /**
     * The organization's transactions, filtered by type, date range, and
     * card code, newest first.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);
        $filters = TransactionFilters::fromRequest($request);

        $transactions = $filters->query($organization)
            ->with(['card:id,code', 'performer:id,name'])
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Transaction $transaction) => self::props($transaction));

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'filters' => $filters->toArray(),
            'types' => TransactionType::visibleValues(),
            'currency' => $organization->currency,
        ]);
    }

    /**
     * The same filtered list as CSV, streamed in chunks. Dates are in the
     * organization's timezone, with its UTC offset.
     */
    public function export(Request $request): StreamedResponse
    {
        $organization = $this->organization($request);
        $filters = TransactionFilters::fromRequest($request);
        $query = $filters->query($organization)->with(['card:id,code', 'performer:id,name,email']);

        $timezone = $organization->timezone;
        $filename = 'transactions-'.now($timezone)->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query, $timezone): void {
            $out = fopen('php://output', 'w');

            // Headers and type names follow the interface language; dates and
            // amounts stay machine-readable (ISO 8601, plain decimals).
            fputcsv($out, [
                __('Date'),
                __('Card'),
                __('Type'),
                __('Amount'),
                __('Balance after'),
                __('Note'),
                __('Performed by'),
            ], escape: '');

            foreach ($query->lazy(500) as $transaction) {
                /** @var Transaction $transaction */
                fputcsv($out, [
                    $transaction->created_at?->setTimezone($timezone)->toIso8601String(),
                    $transaction->card->code,
                    $transaction->type->label(),
                    $transaction->amount,
                    $transaction->balance_after,
                    self::cell($transaction->note),
                    self::cell($transaction->performer->email),
                ], escape: '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array{id: string, card: array{id: string, code: string}, type: string, amount: string, balance_after: string, note: string|null, performed_by: string|null, created_at: string|null}
     */
    public static function props(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'card' => ['id' => $transaction->card->id, 'code' => $transaction->card->code],
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'balance_after' => $transaction->balance_after,
            'note' => $transaction->note,
            'performed_by' => $transaction->performer?->name,
            'created_at' => $transaction->created_at?->toIso8601String(),
        ];
    }

    /**
     * Keep free text from being read as a formula by spreadsheet apps.
     */
    private static function cell(?string $value): ?string
    {
        if ($value !== null && preg_match('/^[=+\-@\t\r]/', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
