<?php

namespace Database\Factories;

use App\Enums\RfcStatus;
use App\Models\Application;
use App\Models\ChangeRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChangeRequestFactory extends Factory
{
    protected $model = ChangeRequest::class;

    public function definition(): array
    {
        return [
            'rfc_number' => 'RFC-'.date('Y').'-'.str_pad((string) $this->faker->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'title' => $this->faker->sentence(),
            'application_id' => Application::factory(),
            'submitted_by_id' => User::factory(),
            'created_by_id' => User::factory(),
            'is_physical_letter' => false,
            'description' => $this->faker->paragraph(),
            'reason_for_change' => $this->faker->paragraph(),
            'impact_description' => $this->faker->paragraph(),
            'change_type' => 'normal',
            'status' => RfcStatus::SUBMITTED->value,
            'target_implementation_date' => now()->addDays(3),
        ];
    }
}
