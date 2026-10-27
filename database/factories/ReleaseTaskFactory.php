<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseTask>
 */
class ReleaseTaskFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_request_id' => ChangeRequest::factory(),
            'release_team_member_id' => ReleaseTeamMember::factory(),
            'assigned_by_id' => User::factory(),
            'instruction' => fake()->paragraph(),
            'scheduled_start' => now()->addDay(),
            'scheduled_end' => now()->addDay()->addHours(2),
            'execution_status' => 'pending',
            'executed_at' => null,
            'execution_notes' => null,
        ];
    }

    public function forRfc(ChangeRequest $rfc): static
    {
        return $this->state(fn (array $attributes): array => [
            'change_request_id' => $rfc->id,
        ]);
    }

    public function assignedTo(ReleaseTeamMember $member): static
    {
        return $this->state(fn (array $attributes): array => [
            'release_team_member_id' => $member->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_status' => 'pending',
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_status' => 'in_progress',
        ]);
    }

    public function successful(): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_status' => 'successful',
            'executed_at' => now(),
        ]);
    }

    public function rolledBack(): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_status' => 'rolled_back',
            'executed_at' => now(),
        ]);
    }
}
