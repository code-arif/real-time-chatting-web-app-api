<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserFactory extends Factory
{
    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'bio' => fake()->optional()->sentence(),
            'phone' => fake()->optional()->phoneNumber(),
            'status' => fake()->randomElement(['online', 'offline', 'away']),
            'last_seen_at' => fake()->dateTimeBetween('-1 hour', 'now'),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function online(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'online',
            'last_seen_at' => now(),
        ]);
    }

    public function offline(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => 'offline',
            'last_seen_at' => fake()->dateTimeBetween('-2 days', '-1 hour'),
        ]);
    }
}
