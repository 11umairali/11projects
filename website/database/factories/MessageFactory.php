<?php

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'workspace_id' => Workspace::factory(),
            'direction' => 'inbound',
            'sender_type' => 'contact',
            'type' => 'message',
            'body_html' => '<p>' . fake()->paragraph() . '</p>',
            'body_text' => fake()->paragraph(),
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'to_emails' => [fake()->safeEmail()],
        ];
    }

    public function outbound(): static
    {
        return $this->state(fn () => [
            'direction' => 'outbound',
            'sender_type' => 'agent',
            'delivery_status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function aiDraft(): static
    {
        return $this->state(fn () => [
            'direction' => 'outbound',
            'sender_type' => 'ai',
            'type' => 'ai_draft',
            'ai_status' => 'draft',
            'ai_confidence' => fake()->numberBetween(70, 95),
            'ai_model' => 'gpt-4o',
            'ai_provider' => 'openai',
        ]);
    }

    public function note(): static
    {
        return $this->state(fn () => [
            'type' => 'note',
            'direction' => 'outbound',
            'sender_type' => 'agent',
        ]);
    }
}
