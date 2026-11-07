<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\ReleaseTask;
use App\Models\ReleaseTaskUpdate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseTaskUpdate>
 */
class ReleaseTaskUpdateFactory extends Factory
{
    protected $model = ReleaseTaskUpdate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_task_id' => ReleaseTask::factory(),
            'change_request_id' => ChangeRequest::factory(),
            'user_id' => User::factory(),
            'type' => 'progress',
            'note' => fake()->sentence(),
            'reason' => null,
            'old_estimated_days' => null,
            'new_estimated_days' => null,
        ];
    }
}
