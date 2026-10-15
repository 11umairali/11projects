<?php

namespace Database\Factories;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CampaignRecipient>
 */
class CampaignRecipientFactory extends Factory
{
    protected $model = CampaignRecipient::class;

    public function definition(): array
    {
        return [
            'campaign_id' => Campaign::factory(),
            'contact_id' => Contact::factory(),
            'status' => 'pending',
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function opened(): static
    {
        return $this->state(fn () => [
            'status' => 'opened',
            'sent_at' => now()->subMinutes(30),
            'opened_at' => now(),
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => 'bounced',
            'bounced_at' => now(),
            'error_message' => 'Mailbox not found',
        ]);
    }
}
