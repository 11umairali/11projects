<?php

namespace Database\Seeders;

use App\Models\Testimonial;
use Illuminate\Database\Seeder;

class TestimonialSeeder extends Seeder
{
    public function run(): void
    {
        $appName = config('app.name', 'App');

        $testimonials = [
            ['client_name' => 'Sarah Chen', 'client_position' => 'Head of Customer Success, TechFlow', 'review' => "{$appName} cut our response time from hours to minutes. The AI suggestions are eerily accurate — it feels like having a senior support rep available 24/7.", 'rating' => 5],
            ['client_name' => 'Marcus Rivera', 'client_position' => 'CEO & Founder, GrowthStack', 'review' => "We switched from three different tools to {$appName}. The unified inbox alone saved us 15 hours per week. The workflow automation is the cherry on top.", 'rating' => 5],
            ['client_name' => 'Emily Watson', 'client_position' => 'Marketing Director, Vertex Labs', 'review' => 'The campaign builder is miles ahead. A/B testing with AI-optimized subject lines increased our open rates by 34% in the first month.', 'rating' => 5],
            ['client_name' => 'Raj Patel', 'client_position' => 'Lead Engineer, CloudNine', 'review' => 'As a developer, I appreciate the clean API and webhook system. Integration took less than a day. The documentation is excellent.', 'rating' => 5],
            ['client_name' => 'Amanda Foster', 'client_position' => 'VP of Sales, Horizon SaaS', 'review' => "Our sales team lives in {$appName} now. CRM integration with email tracking and lead scoring helped us close 28% more deals this quarter.", 'rating' => 5],
            ['client_name' => 'Kenji Nakamura', 'client_position' => 'Operations Manager, Nexus Digital', 'review' => 'Managing WhatsApp, email, and Telegram from one place — with AI-drafted replies — is pure magic.', 'rating' => 5],
        ];

        foreach ($testimonials as $t) {
            Testimonial::firstOrCreate(
                ['client_name' => $t['client_name']],
                array_merge($t, ['is_active' => true])
            );
        }
    }
}
