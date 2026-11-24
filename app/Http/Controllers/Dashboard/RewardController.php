<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\FlashMessage;
use App\Http\Controllers\Controller;
use App\Http\Requests\Rewards\SaveRewardRequest;
use App\Models\Organization;
use App\Models\Reward;
use App\Support\CurrentOrganization;
use App\Support\Flash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The reward catalog: what a card's points buy. Owners and managers see and
 * edit it; rewards are switched off rather than deleted, so a redemption's
 * history keeps pointing at them.
 */
class RewardController extends Controller
{
    public function index(Request $request): Response
    {
        $organization = $this->organization($request);

        Gate::authorize('viewRewards', $organization);

        $program = $organization->defaultProgram();

        $rewards = [];
        $enabled = false;

        if ($program !== null) {
            $enabled = $program->rewards_enabled;
            $rewards = $program->rewards()
                ->byCost()
                ->get()
                ->map(fn (Reward $reward) => self::props($reward))
                ->all();
        }

        return Inertia::render('rewards/index', [
            'rewards' => $rewards,
            'rewardsEnabled' => $enabled,
            'canEdit' => Gate::allows('update', $organization),
            'maxCost' => SaveRewardRequest::MAX_COST,
        ]);
    }

    public function store(SaveRewardRequest $request): RedirectResponse
    {
        $program = $this->organization($request)->defaultProgram();

        if ($program === null) {
            throw ValidationException::withMessages([
                'program' => __('This business has no active program to issue cards from.'),
            ]);
        }

        $program->rewards()->create($request->toAttributes());

        Flash::success(FlashMessage::RewardSaved);

        return to_route('rewards.index');
    }

    public function update(SaveRewardRequest $request, Reward $reward): RedirectResponse
    {
        $this->ownReward($request, $reward);

        $reward->update($request->toAttributes());

        Flash::success(FlashMessage::RewardSaved);

        return to_route('rewards.index');
    }

    /**
     * @return array{id: string, name: string, points_cost: int, is_active: bool}
     */
    public static function props(Reward $reward): array
    {
        return [
            'id' => $reward->id,
            'name' => $reward->name,
            'points_cost' => $reward->points_cost,
            'is_active' => $reward->is_active,
        ];
    }

    /**
     * A reward of another business is a 404, like any other record that is
     * not yours.
     */
    private function ownReward(Request $request, Reward $reward): void
    {
        $organization = $this->organization($request);

        abort_unless($reward->program()->where('organization_id', $organization->id)->exists(), 404);
    }

    private function organization(Request $request): Organization
    {
        $organization = CurrentOrganization::organization($request);

        abort_if($organization === null, 403);

        return $organization;
    }
}
