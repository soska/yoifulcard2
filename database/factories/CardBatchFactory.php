<?php

namespace Database\Factories;

use App\Models\CardBatch;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Rows made here skip CardBatchIssuer: they have no cards and check no
 * limits. Give them cards with `Card::factory()->for($batch, 'batch')`.
 *
 * @extends Factory<CardBatch>
 */
class CardBatchFactory extends Factory
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
            'organization_id' => fn (array $attributes) => Program::query()->whereKey($attributes['program_id'])->value('organization_id'),
            'count' => 0,
            'template' => null,
            'created_by' => User::factory(),
            'issued_by_admin' => false,
            'notes' => null,
            'voided_at' => null,
        ];
    }
}
