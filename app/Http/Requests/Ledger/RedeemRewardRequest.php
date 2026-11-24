<?php

namespace App\Http\Requests\Ledger;

use App\Models\Card;
use App\Models\Reward;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Redeem a reward from the reader. CardLedger checks that the reward is
 * active and the program's own, and that the card has the points.
 */
class RedeemRewardRequest extends FormRequest
{
    /**
     * Only members of the card's organization, while it is active. This runs
     * before validation, so outsiders get a 403 whatever they send.
     */
    public function authorize(): bool
    {
        $card = $this->route('card');

        return $card instanceof Card && $this->user()?->can('transact', $card) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reward_id' => ['required', 'uuid'],
        ];
    }

    /**
     * The chosen reward, or null when the id names nothing. CardLedger refuses
     * a missing reward like any other one that is not available.
     */
    public function reward(): ?Reward
    {
        return Reward::query()->find((string) $this->validated('reward_id'));
    }
}
