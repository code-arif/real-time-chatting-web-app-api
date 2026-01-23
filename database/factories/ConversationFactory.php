<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConversationFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['private', 'group']);

        return [
            'type' => $type,
            'name' => $type === 'group' ? fake()->words(3, true) : null,
            'description' => $type === 'group' ? fake()->optional()->sentence() : null,
            'created_by' => User::factory(),
            'last_message_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    public function private(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'private',
            'name' => null,
            'description' => null,
        ]);
    }

    public function group(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'group',
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
        ]);
    }
}
