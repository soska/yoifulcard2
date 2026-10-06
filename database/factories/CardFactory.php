<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\Organization;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'program_id' => Program::factory(),
            'code' => 'YGFT-'.fake()->unique()->regexify('[A-HJKMNP-Z2-9]{6}'),
            'qr_token' => fake()->unique()->regexify('[A-Za-z0-9_-]{64}'),
            'balance' => '0.00',
            'status' => CardStatus::Active,
            'email' => null,
            'last_used_at' => null,
        ];
    }

    /**
     * Put the card in the organization's first program, creating one if needed.
     */
    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => [
            'program_id' => $organization->programs()->oldest()->value('id')
                ?? Program::factory()->for($organization)->create()->id,
        ]);
    }

    public function frozen(): static
    {
        return $this->state(fn () => ['status' => CardStatus::Frozen]);
    }

    /**
     * A preissued card that has not been activated: no balance yet.
     */
    public function inactive(): static
    {
        return $this->state(fn () => ['status' => CardStatus::Inactive, 'balance' => '0.00']);
    }
}
