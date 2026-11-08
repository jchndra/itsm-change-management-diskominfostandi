<?php

namespace Database\Factories;

use App\Models\ChangeRequest;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'change_request_id' => ChangeRequest::factory(),
            'user_id' => User::factory(),
            'comment' => fake()->sentence(8),
            'is_internal' => false,
        ];
    }

    public function forRfc(ChangeRequest $rfc): static
    {
        return $this->state(fn (array $attributes): array => [
            'change_request_id' => $rfc->id,
        ]);
    }

    public function internal(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_internal' => true,
        ]);
    }
}
