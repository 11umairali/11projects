<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campaign>
 */
class CampaignFactory extends Factory
{
    protected $model = Campaign::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'created_by' => User::factory(),
            'email_account_id' => EmailAccount::factory(),
            'name' => fake()->words(3, true) . ' Campaign',
            'type' => 'regular',
            'status' => 'draft',
            'subject' => fake()->sentence(),
            'body_html' => '<h1>' . fake()->sentence() . '</h1><p>' . fake()->paragraph() . '</p>',
            'audience_type' => 'all',
        ];
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => 'scheduled',
            'scheduled_at' => now()->addHours(2),
        ]);
    }

    public function sending(): static
    {
        return $this->state(fn () => [
            'status' => 'sending',
            'sent_at' => now(),
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_at' => now()->subHour(),
            'completed_at' => now(),
            'recipients_count' => 100,
            'sent_count' => 95,
            'delivered_count' => 90,
            'opened_count' => 45,
            'clicked_count' => 12,
            'bounced_count' => 5,
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => 'paused']);
    }

    public function abTest(): static
    {
        return $this->state(fn () => ['type' => 'ab_test']);
    }
}
