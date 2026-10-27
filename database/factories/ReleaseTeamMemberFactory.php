<?php

namespace Database\Factories;

use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseTeamMember>
 */
class ReleaseTeamMemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => null,
            'name' => fake()->name(),
            'nip_or_nik' => fake()->numerify('################'),
            'role_title' => fake()->randomElement([
                'DevOps Engineer',
                'Database Administrator',
                'Application Support',
            ]),
            'phone_number' => fake()->numerify('08##########'),
            'is_active' => true,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function linkedTo(User $user): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user->id,
        ]);
    }
}
