<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    public function definition(): array
    {
        $type = fake()->randomElement(['text', 'text', 'text', 'text', 'image', 'video', 'file']);

        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'type' => $type,
            'content' => $type === 'text' ? fake()->sentence() : fake()->optional()->words(3, true),
            'media_path' => in_array($type, ['image', 'video', 'file']) ? 'media/' . fake()->uuid() . $this->getExtension($type) : null,
            'media_name' => in_array($type, ['image', 'video', 'file']) ? fake()->word() . $this->getExtension($type) : null,
            'media_type' => $this->getMediaType($type),
            'media_size' => in_array($type, ['image', 'video', 'file']) ? fake()->numberBetween(1024, 5242880) : null,
            'is_edited' => fake()->boolean(10),
            'edited_at' => fake()->boolean(10) ? fake()->dateTimeBetween('-1 day', 'now') : null,
            'created_at' => fake()->dateTimeBetween('-1 week', 'now'),
        ];
    }

    private function getExtension(string $type): string
    {
        return match ($type) {
            'image' => '.jpg',
            'video' => '.mp4',
            'file' => '.pdf',
            default => '',
        };
    }

    private function getMediaType(string $type): ?string
    {
        return match ($type) {
            'image' => 'image/jpeg',
            'video' => 'video/mp4',
            'file' => 'application/pdf',
            default => null,
        };
    }

    public function text(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'text',
            'content' => fake()->sentence(),
            'media_path' => null,
            'media_name' => null,
            'media_type' => null,
            'media_size' => null,
        ]);
    }

    public function image(): static
    {
        return $this->state(fn(array $attributes) => [
            'type' => 'image',
            'content' => fake()->optional()->sentence(),
            'media_path' => 'media/' . fake()->uuid() . '.jpg',
            'media_name' => fake()->word() . '.jpg',
            'media_type' => 'image/jpeg',
            'media_size' => fake()->numberBetween(102400, 2097152),
        ]);
    }
}
