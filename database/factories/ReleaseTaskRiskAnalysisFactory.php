<?php

namespace Database\Factories;

use App\Models\ReleaseTask;
use App\Models\ReleaseTaskRiskAnalysis;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReleaseTaskRiskAnalysis>
 */
class ReleaseTaskRiskAnalysisFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'release_task_id' => ReleaseTask::factory(),
            'submitted_by_id' => User::factory(),
            'activity_plan' => 'Menyiapkan cadangan basis data, lalu menerapkan perubahan pada server produksi.',
            'change_plan' => 'Memperbarui konfigurasi layanan dan menjalankan pengujian fungsional.',
            'rollback_mechanism' => 'Memulihkan cadangan basis data dan mengembalikan konfigurasi sebelumnya.',
        ];
    }
}
