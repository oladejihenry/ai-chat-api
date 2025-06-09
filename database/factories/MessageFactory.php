<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Conversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'content' => $this->faker->paragraph(),
            'role' => $this->faker->randomElement(['user', 'assistant']),
            'model_name' => $this->faker->optional()->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet']),
        ];
    }

    /**
     * Indicate that the message is from a user.
     */
    public function user(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'user',
            'model_name' => null,
        ]);
    }

    /**
     * Indicate that the message is from an assistant.
     */
    public function assistant(): static
    {
        return $this->state(fn(array $attributes) => [
            'role' => 'assistant',
            'model_name' => $this->faker->randomElement(['gpt-4', 'gpt-3.5-turbo', 'claude-3-sonnet']),
        ]);
    }
}
