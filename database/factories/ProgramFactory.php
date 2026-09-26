<?php

namespace Database\Factories;

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
        ];
    }
}
