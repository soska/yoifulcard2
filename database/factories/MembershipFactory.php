<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Membership>
 */
class MembershipFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => MembershipRole::Owner,
        ];
    }

    public function owner(): static
    {
        return $this->state(fn () => ['role' => MembershipRole::Owner]);
    }

    public function manager(): static
    {
        return $this->state(fn () => ['role' => MembershipRole::Manager]);
    }

    public function employee(): static
    {
        return $this->state(fn () => ['role' => MembershipRole::Employee]);
    }
}
