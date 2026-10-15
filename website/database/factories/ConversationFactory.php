<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'contact_id' => Contact::factory(),
            'channel' => 'email',
            'status' => 'open',
            'priority' => 'normal',
            'subject' => fake()->sentence(),
            'preview' => fake()->text(200),
            'is_read' => false,
            'messages_count' => 1,
            'last_message_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => 'closed',
            'resolved_at' => now(),
        ]);
    }

    public function starred(): static
    {
        return $this->state(fn () => ['is_starred' => true]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['is_read' => true]);
    }
}
