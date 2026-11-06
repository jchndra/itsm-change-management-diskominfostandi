<?php

namespace Database\Factories;

use App\Models\Opd;
use Illuminate\Database\Eloquent\Factories\Factory;

class OpdFactory extends Factory
{
    protected $model = Opd::class;

    public function definition(): array
    {
        return [
            'code' => 'OPD-'.$this->faker->unique()->numberBetween(100, 999),
            'name' => 'Dinas '.$this->faker->company(),
            'is_active' => true,
        ];
    }
}
