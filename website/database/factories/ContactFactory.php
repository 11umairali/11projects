<?php

namespace Database\Factories;

use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'company' => fake()->company(),
            'job_title' => fake()->jobTitle(),
            'city' => fake()->city(),
            'country' => fake()->countryCode(),
            'lead_score' => fake()->numberBetween(0, 100),
            'status' => 'active',
        ];
    }

    public function unsubscribed(): static
    {
        return $this->state(fn () => [
            'status' => 'unsubscribed',
            'unsubscribed_at' => now(),
            'unsubscribe_reason' => 'no longer interested',
        ]);
    }

    public function bounced(): static
    {
        return $this->state(fn () => [
            'status' => 'bounced',
        ]);
    }

    public function withoutEmail(): static
    {
        return $this->state(fn () => [
            'email' => null,
        ]);
    }
}
