<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CardStatus;
use App\Enums\FlashMessage;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\ProgramType;
use App\Enums\TransactionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrganizationRequest;
use App\Http\Requests\Admin\UpdateOrganizationPlanRequest;
use App\Models\Card;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Program;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Flash;
use App\Support\OneTimeCredentials;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public const PER_PAGE = 20;

    /**
     * Every organization, newest first, searchable by name or slug and
     * filterable by status. List state lives in the query string.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));
        $status = OrganizationStatus::tryFrom((string) $request->query('status', ''));

        $organizations = Organization::query()
            ->withCount(['memberships', 'cards'])
            ->when($status, fn (Builder $query, OrganizationStatus $status) => $query->where('status', $status))
            ->when($search !== '', function (Builder $query) use ($search): void {
                $pattern = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';

                $query->where(fn (Builder $query) => $query
                    ->whereRaw('lower(name) like ?', [$pattern])
                    ->orWhereRaw('lower(slug) like ?', [$pattern]));
            })
            ->latest()
            ->orderBy('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (Organization $organization) => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'card_limit' => $organization->card_limit,
                'members_count' => (int) $organization->getAttribute('memberships_count'),
                'cards_count' => (int) $organization->getAttribute('cards_count'),
                'created_at' => $organization->created_at?->toIso8601String(),
            ]);

        return Inertia::render('admin/organizations/index', [
            'organizations' => $organizations,
            'filters' => [
                'q' => $search,
                'status' => $status?->value,
            ],
            'statuses' => array_column(OrganizationStatus::cases(), 'value'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/organizations/create');
    }

    /**
     * Create an organization, its owner membership, and the default program.
     * An owner email that matches a user links that user; a new email creates
     * the user with a generated password, which is shown once.
     */
    public function store(StoreOrganizationRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $password = null;

        $organization = DB::transaction(function () use ($data, &$password): Organization {
            $owner = User::query()->whereRaw('lower(email) = ?', [$data['owner_email']])->first();

            if ($owner === null) {
                $password = OneTimeCredentials::generatePassword();

                $owner = User::create([
                    'name' => $data['owner_name'] ?: Str::before($data['owner_email'], '@'),
                    'email' => $data['owner_email'],
                    'password' => $password,
                ]);
            }

            $organization = Organization::create([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'card_limit' => $data['card_limit'] ?? null,
                'plan_notes' => $data['plan_notes'] ?? null,
            ]);

            $organization->memberships()->create([
                'user_id' => $owner->id,
                'role' => MembershipRole::Owner,
            ]);

            $organization->programs()->create([
                'name' => __('Gift Card'),
                'type' => ProgramType::Prepaid,
            ]);

            return $organization;
        });

        if ($password !== null) {
            OneTimeCredentials::put($request, $data['owner_email'], $password);
            Flash::success(FlashMessage::OrganizationCreatedWithNewOwner);
        } else {
            Flash::success(FlashMessage::OrganizationCreatedForExistingOwner, ['email' => $data['owner_email']]);
        }

        return to_route('admin.organizations.show', $organization);
    }

    /**
     * One organization: status, usage, plan, members, and programs.
     */
    public function show(Request $request, Organization $organization): Response
    {
        OneTimeCredentials::release($request);

        $cards = Card::query()
            ->forOrganization($organization)
            ->toBase()
            ->selectRaw('count(*) filter (where status = ?) as active', [CardStatus::Active->value])
            ->selectRaw('count(*) filter (where status = ?) as frozen', [CardStatus::Frozen->value])
            ->selectRaw('count(*) filter (where status = ?) as depleted', [CardStatus::Depleted->value])
            ->selectRaw('count(*) filter (where status = ?) as cancelled', [CardStatus::Cancelled->value])
            ->selectRaw('coalesce(sum(balance), 0) as balance')
            ->first();

        $transactions = Transaction::query()
            ->forOrganization($organization)
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('coalesce(sum(amount) filter (where type = ?), 0) as loaded', [TransactionType::Load->value])
            ->first();

        return Inertia::render('admin/organizations/show', [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
                'status' => $organization->status->value,
                'currency' => $organization->currency,
                'timezone' => $organization->timezone,
                'primary_color' => $organization->primary_color,
                'logo_url' => $organization->logo_url,
                'card_limit' => $organization->card_limit,
                'plan_notes' => $organization->plan_notes,
                'created_at' => $organization->created_at?->toIso8601String(),
                'updated_at' => $organization->updated_at?->toIso8601String(),
            ],
            'usage' => $organization->cardUsage(),
            'stats' => [
                'active' => (int) $cards->active,
                'frozen' => (int) $cards->frozen,
                'depleted' => (int) $cards->depleted,
                'cancelled' => (int) $cards->cancelled,
                'outstandingBalance' => self::decimal($cards->balance),
                'transactions' => (int) $transactions->total,
                'loaded' => self::decimal($transactions->loaded),
            ],
            'members' => $organization->memberships()
                ->with('user:id,name,email')
                ->oldest()
                ->oldest('id')
                ->get()
                ->map(fn (Membership $membership) => [
                    'id' => $membership->id,
                    'role' => $membership->role->value,
                    'name' => $membership->user?->name,
                    'email' => $membership->user?->email,
                    'user_id' => $membership->user_id,
                ]),
            'programs' => $organization->programs()
                ->oldest()
                ->oldest('id')
                ->get()
                ->map(fn (Program $program) => [
                    'id' => $program->id,
                    'name' => $program->name,
                    'type' => $program->type->value,
                    'is_active' => (bool) $program->is_active,
                ]),
        ]);
    }

    /**
     * Set the card limit (empty means unlimited) and the plan notes.
     */
    public function update(UpdateOrganizationPlanRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            'card_limit' => $request->validated('card_limit') === null ? null : (int) $request->validated('card_limit'),
            'plan_notes' => $request->validated('plan_notes'),
        ]);

        Flash::success(FlashMessage::PlanSaved);

        return to_route('admin.organizations.show', $organization);
    }

    /**
     * Suspend an active organization. Its members can still sign in and read
     * their data, but nothing can change.
     */
    public function suspend(Organization $organization): RedirectResponse
    {
        return $this->changeStatus($organization, OrganizationStatus::Active, OrganizationStatus::Suspended, FlashMessage::OrganizationSuspended);
    }

    /**
     * Reactivate a suspended organization.
     */
    public function reactivate(Organization $organization): RedirectResponse
    {
        return $this->changeStatus($organization, OrganizationStatus::Suspended, OrganizationStatus::Active, FlashMessage::OrganizationReactivated);
    }

    /**
     * The admin area only moves between active and suspended. Cancelled
     * organizations are left alone.
     */
    private function changeStatus(Organization $organization, OrganizationStatus $from, OrganizationStatus $to, FlashMessage $message): RedirectResponse
    {
        DB::transaction(function () use ($organization, $from, $to): void {
            $locked = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            if ($locked->status !== $from) {
                throw ValidationException::withMessages([
                    'status' => match ($locked->status) {
                        OrganizationStatus::Active => __('This organization is already active.'),
                        OrganizationStatus::Suspended => __('This organization is already suspended.'),
                        OrganizationStatus::Cancelled => __('This organization is cancelled.'),
                    },
                ]);
            }

            $locked->update(['status' => $to]);
        });

        Flash::success($message);

        return to_route('admin.organizations.show', $organization);
    }

    /**
     * A database decimal as a two-place string, without going through float.
     */
    private static function decimal(mixed $value): string
    {
        return bcadd((string) $value, '0', 2);
    }
}
