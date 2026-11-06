<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Opd;
use Illuminate\Database\Eloquent\Factories\Factory;

class ApplicationFactory extends Factory
{
    protected $model = Application::class;

    public function definition(): array
    {
        return [
            'opd_id' => Opd::factory(),
            'code' => 'APP-'.$this->faker->unique()->numberBetween(1000, 9999),
            'name' => 'Sistem '.$this->faker->words(2, true),
            'description' => 'Aplikasi untuk keperluan pengujian.',
            'criticality_level' => 'medium',
            'is_active' => true,
        ];
    }
}
