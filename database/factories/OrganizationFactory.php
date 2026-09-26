<?php

namespace Database\Factories;

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'primary_color' => '#000000',
            'currency' => 'MXN',
            'status' => OrganizationStatus::Active,
            'card_limit' => null,
            'plan_notes' => null,
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Suspended]);
    }

    public function cancelled(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Cancelled]);
    }

    /**
     * Give the organization a member with the given role.
     */
    public function withMember(User $user, MembershipRole $role = MembershipRole::Owner): static
    {
        return $this->afterCreating(function (Organization $organization) use ($user, $role): void {
            Membership::factory()->for($user)->for($organization)->create(['role' => $role]);
        });
    }
}
