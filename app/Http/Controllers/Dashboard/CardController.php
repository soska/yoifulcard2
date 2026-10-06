<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CardStatus;
use App\Enums\FlashMessage;
use App\Exceptions\LedgerException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cards\StoreCardRequest;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Models\Transaction;
use App\Services\CardBatchIssuer;
use App\Services\CardCodeGenerator;
use App\Services\CardLedger;
use App\Services\CardLimit;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * How many recent transactions the card page shows. The full history is
     * on the transactions page, filtered by the card's code.
     */
    public const HISTORY_LIMIT = 20;

    public const SORTS = ['code', 'balance', 'created_at', 'last_used_at'];

    /**
     * The organization's cards, sorted, filtered, searched, and paginated.
     * All list state lives in the query string.
     */
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        $sort = in_array($request->query('sort'), self::SORTS, true) ? $request->query('sort') : 'created_at';
        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';
        $status = CardStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));
        $batch = $this->batchFilter($request, $organization);

        $cards = Card::query()
            ->forOrganization($organization)
            ->when($status, fn (Builder $query, CardStatus $status) => $query->where('status', $status))
            ->when($batch, fn (Builder $query, CardBatch $batch) => $query->where('batch_id', $batch->id))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(code) like ?', [$pattern])
                    ->orWhereRaw('lower(email) like ?', [$pattern]));
            })
            // Cards that were never used sort last in both directions.
            ->orderByRaw($sort.' '.$direction.($sort === 'last_used_at' ? ' nulls last' : ''))
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Card $card) => self::cardProps($card));

        return Inertia::render('cards/index', [
            'cards' => $cards,
            'filters' => [
                'sort' => $sort,
                'direction' => $direction,
                'status' => $status?->value,
                'q' => $search,
                'batch' => $batch?->id,
            ],
            'batch' => $batch === null ? null : [
                'id' => $batch->id,
                'count' => $batch->count,
                'created_at' => $batch->created_at?->toIso8601String(),
            ],
            'statuses' => CardStatus::values(),
            'currency' => $organization->currency,
            'usage' => $organization->cardUsage(),
        ]);
    }

    public function create(Request $request): Response
    {
        $organization = $this->organization($request);

        return Inertia::render('cards/create', [
            'currency' => $organization->currency,
            'usage' => $organization->cardUsage(),
        ]);
    }

    /**
     * Create a card in the organization's default program, unless the
     * organization has reached its card limit. A nonzero initial balance is
     * posted as a load in the same database transaction as the card insert;
     * it does not count as a use, so the card shows "Never" until it is used.
     */
    public function store(StoreCardRequest $request, CardCodeGenerator $generator, CardLedger $ledger): RedirectResponse
    {
        $organization = $this->organization($request);

        Gate::authorize('create', [Card::class, $organization]);

        $card = DB::transaction(function () use ($organization, $request, $generator, $ledger): Card {
            // Lock the organization so two requests cannot both take the last slot.
            try {
                $organization = CardLimit::reserve($organization->id);
            } catch (LedgerException $exception) {
                throw ValidationException::withMessages([
                    $exception->field => $exception->translated(),
                ]);
            }

            $program = $organization->defaultProgram();

            if ($program === null) {
                throw ValidationException::withMessages([
                    'program' => __('This business has no active program to issue cards from.'),
                ]);
            }

            $card = $program->cards()->create([
                'code' => $generator->code(),
                'qr_token' => $generator->qrToken(),
                'balance' => '0.00',
                'status' => CardStatus::Active,
                'email' => $request->validated('email'),
            ]);

            if (bccomp($request->initialBalance(), '0', 2) > 0) {
                try {
                    $ledger->issue($card, $request->initialBalance(), $request->user());
                } catch (LedgerException $exception) {
                    throw ValidationException::withMessages([
                        $exception->field === 'amount' ? 'initial_balance' : $exception->field => $exception->translated(),
                    ]);
                }
            }

            return $card;
        });

        Flash::success(FlashMessage::CardCreated, ['code' => $card->code]);

        return to_route('cards.show', $card);
    }

    public function show(Request $request, Card $card): Response
    {
        Gate::authorize('view', $card);

        $card->loadMissing('program.organization');

        $transactions = $card->transactions()
            ->with('performer:id,name')
            ->latest()
            ->orderByDesc('id')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->each(fn (Transaction $transaction) => $transaction->setRelation('card', $card))
            ->map(fn (Transaction $transaction) => TransactionController::props($transaction));

        return Inertia::render('cards/show', [
            'card' => [
                ...self::cardProps($card),
                'program' => $card->program->name,
                'updated_at' => $card->updated_at?->toIso8601String(),
            ],
            'currency' => $card->program->organization->currency,
            'transactions' => $transactions,
            'transactionCount' => $card->transactions()->count(),
            'canVoid' => $card->status === CardStatus::Inactive && $request->user()->can('void', $card),
            'canViewBatch' => $card->batch_id !== null && $request->user()->can('viewBatches', $card->program->organization),
        ]);
    }

    /**
     * Only an active or depleted card can be frozen; an inactive card is
     * refused like any other status.
     */
    public function freeze(Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        if ($card->status !== CardStatus::Active && $card->status !== CardStatus::Depleted) {
            throw ValidationException::withMessages([
                'status' => __('Only an active card can be frozen.'),
            ]);
        }

        $card->update(['status' => CardStatus::Frozen]);

        Flash::success(FlashMessage::CardFrozen);

        return back();
    }

    public function unfreeze(Card $card): RedirectResponse
    {
        Gate::authorize('update', $card);

        if ($card->status !== CardStatus::Frozen) {
            throw ValidationException::withMessages([
                'status' => __('Only a frozen card can be unfrozen.'),
            ]);
        }

        $card->update(['status' => CardStatus::Active]);

        Flash::success(FlashMessage::CardUnfrozen);

        return back();
    }

    /**
     * Void a card that is not activated yet (lost or stolen stock): it
     * becomes cancelled and stops counting toward the preissue limit.
     */
    public function void(Card $card, CardBatchIssuer $issuer): RedirectResponse
    {
        Gate::authorize('void', $card);

        CardBatchController::attempt(fn () => $issuer->voidCard($card));

        Flash::success(FlashMessage::CardVoided, ['code' => $card->code]);

        return back();
    }

    /**
     * The fields a page may see. The QR token is never among them.
     *
     * @return array{id: string, code: string, balance: string, status: string, email: string|null, batch_id: string|null, created_at: string|null, last_used_at: string|null, activated_at: string|null}
     */
    public static function cardProps(Card $card): array
    {
        return [
            'id' => $card->id,
            'code' => $card->code,
            'balance' => $card->balance,
            'status' => $card->status->value,
            'email' => $card->email,
            'batch_id' => $card->batch_id,
            'created_at' => $card->created_at?->toIso8601String(),
            'last_used_at' => $card->last_used_at?->toIso8601String(),
            'activated_at' => $card->activated_at?->toIso8601String(),
        ];
    }

    /**
     * The batch named by `?batch=`, when it belongs to the organization.
     * Anything else is ignored, like an unknown status.
     */
    private function batchFilter(Request $request, Organization $organization): ?CardBatch
    {
        $id = (string) $request->query('batch', '');

        if (! Str::isUuid($id)) {
            return null;
        }

        return $organization->cardBatches()->whereKey($id)->first();
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
