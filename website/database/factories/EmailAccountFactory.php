<?php

namespace Database\Factories;

use App\Models\EmailAccount;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAccount>
 */
class EmailAccountFactory extends Factory
{
    protected $model = EmailAccount::class;

    public function definition(): array
    {
        return [
            'workspace_id' => Workspace::factory(),
            'user_id' => User::factory(),
            'email' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'provider' => 'imap',
            'smtp_host' => 'smtp.test.com',
            'smtp_port' => 587,
            'smtp_encryption' => 'tls',
            'status' => 'connected',
            'is_default' => true,
        ];
    }

    public function gmail(): static
    {
        return $this->state(fn () => ['provider' => 'gmail']);
    }

    public function disconnected(): static
    {
        return $this->state(fn () => ['status' => 'disconnected']);
    }
}
