<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\CardStatus;
use App\Enums\FlashMessage;
use App\Exceptions\CardBatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\CardBatches\StoreCardBatchRequest;
use App\Models\Card;
use App\Models\CardBatch;
use App\Models\Organization;
use App\Services\CardBatchIssuer;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Card batches for a business that can preissue cards (can_preissue): owners
 * and managers list, create, and void them. Superadmins do the same for any
 * business from Admin\CardBatchController.
 */
class CardBatchController extends Controller
{
    public const PER_PAGE = 20;

    public function __construct(private readonly CardBatchIssuer $issuer) {}

    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        Gate::authorize('viewBatches', $organization);

        $batches = self::withCounts($organization->cardBatches())
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (CardBatch $batch) => self::props($batch));

        return Inertia::render('batches/index', [
            'batches' => $batches,
            'usage' => $organization->cardUsage(),
            'preissueLimit' => $organization->preissue_limit,
            'maxBatchSize' => CardBatchIssuer::MAX_BATCH_SIZE,
            'canCreate' => Gate::allows('preissue', $organization),
        ]);
    }

    /**
     * Create a batch of inactive cards. Refused above the preissue limit;
     * stock beyond the room left under the card limit only warns.
     */
    public function store(StoreCardBatchRequest $request): RedirectResponse
    {
        $organization = $this->organization($request);

        Gate::authorize('preissue', $organization);

        $batch = self::attempt(fn () => $this->issuer->issue($organization, $request->cardCount(), $request->user(), byAdmin: false));

        self::flashCreated($batch);

        return to_route('batches.show', $batch);
    }

    public function show(Request $request, CardBatch $batch): Response
    {
        Gate::authorize('viewBatches', $batch->organization);

        return Inertia::render('batches/show', [
            ...self::showProps($batch, $request),
            'canVoid' => Gate::allows('preissue', $batch->organization),
        ]);
    }

    /**
     * Cancel every card in the batch that is not activated yet.
     */
    public function void(CardBatch $batch): RedirectResponse
    {
        Gate::authorize('preissue', $batch->organization);

        $cancelled = self::attempt(fn () => $this->issuer->void($batch));

        Flash::success(FlashMessage::CardBatchVoided, ['count' => $cancelled]);

        return back();
    }

    /**
     * Add the activated and in-stock counts and the creator's name.
     *
     * @template TQuery of Builder<CardBatch>|Relation<CardBatch, *, *>
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    public static function withCounts(Builder|Relation $query): Builder|Relation
    {
        return $query
            ->with('creator:id,name')
            ->withCount([
                'cards as activated_count' => fn (Builder $query) => $query->whereNotNull('activated_at'),
                'cards as stock_count' => fn (Builder $query) => $query->where('status', CardStatus::Inactive),
            ]);
    }

    /**
     * A batch as pages see it, from a model loaded through withCounts().
     * Notes are for superadmins and are left out.
     *
     * @return array{id: string, count: int, activated: int, stock: int, created_by: string, issued_by_admin: bool, voided_at: string|null, created_at: string|null}
     */
    public static function props(CardBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'count' => $batch->count,
            'activated' => (int) $batch->getAttribute('activated_count'),
            'stock' => (int) $batch->getAttribute('stock_count'),
            'created_by' => $batch->creator->name,
            'issued_by_admin' => $batch->issued_by_admin,
            'voided_at' => $batch->voided_at?->toIso8601String(),
            'created_at' => $batch->created_at?->toIso8601String(),
        ];
    }

    /**
     * The batch and a page of its cards, for the business and admin pages.
     *
     * @return array{batch: array<string, mixed>, cards: mixed, currency: string}
     */
    public static function showProps(CardBatch $batch, Request $request): array
    {
        $batch = self::withCounts(CardBatch::query())->findOrFail($batch->id);

        $cards = $batch->cards()
            ->orderBy('code')
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Card $card) => CardController::cardProps($card));

        return [
            'batch' => self::props($batch),
            'cards' => $cards,
            'currency' => $batch->organization->currency,
        ];
    }

    /**
     * Toast for a new batch. When the stock is larger than the room left
     * under the card limit, the toast says how many cards can't be activated
     * yet instead.
     */
    public static function flashCreated(CardBatch $batch): void
    {
        Flash::success(FlashMessage::CardBatchCreated, [
            'count' => $batch->count,
            'beyond' => CardBatchIssuer::stockBeyondRoom($batch->organization),
        ]);
    }

    /**
     * Run an issuer call and turn its refusal into a validation error.
     *
     * @template T
     *
     * @param  Closure(): T  $action
     * @return T
     */
    public static function attempt(Closure $action): mixed
    {
        try {
            return $action();
        } catch (CardBatchException $exception) {
            throw ValidationException::withMessages([
                $exception->field => $exception->translated(),
            ]);
        }
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
