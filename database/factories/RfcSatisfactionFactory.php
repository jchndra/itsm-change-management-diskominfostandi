<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\RfcSatisfaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RfcSatisfaction>
 */
class RfcSatisfactionFactory extends Factory
{
    protected $model = RfcSatisfaction::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_request_id' => ChangeRequest::factory(),
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'comment' => fake()->optional()->sentence(),
        ];
    }
}
