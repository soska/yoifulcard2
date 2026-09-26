<?php

namespace Database\Factories;

use App\Models\Superadmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Superadmin>
 */
class SuperadminFactory extends Factory
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
            'granted_at' => now(),
            'granted_by' => null,
        ];
    }
}
