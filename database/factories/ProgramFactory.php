<?php

namespace Database\Factories;

use App\Enums\EarnMode;
use App\Enums\ProgramType;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Gift Card',
            'type' => ProgramType::Prepaid,
            'is_active' => true,
            'terms_url' => null,
            'rewards_enabled' => false,
            'earn_mode' => null,
            'earn_rate' => null,
            'points_expiry_months' => null,
        ];
    }

    /**
     * Rewards on. The rate only applies to the spend mode.
     */
    public function withRewards(EarnMode $mode = EarnMode::Spend, ?string $rate = '1.00'): static
    {
        return $this->state(fn () => [
            'rewards_enabled' => true,
            'earn_mode' => $mode,
            'earn_rate' => $mode === EarnMode::Spend ? $rate : null,
        ]);
    }
}
