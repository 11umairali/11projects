<?php

namespace Database\Seeders;

use App\Models\Page;
use Illuminate\Database\Seeder;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $name = config('app.name', 'App');

        $pages = [
            // Footer
            [
                'type' => 'footer', 'title' => 'Footer', 'slug' => 'footer',
                'content' => [
                    'description' => 'AI-powered email automation, CRM, and multi-channel communication platform for modern teams.',
                    'twitter_url' => '',
                    'github_url' => '',
                    'linkedin_url' => '',
                    'facebook_url' => '',
                    'instagram_url' => '',
                    'newsletter_enabled' => 'true',
                    'newsletter_title' => 'Stay Updated',
                    'newsletter_subtitle' => 'Get the latest updates on features, tips, and product news.',
                    'copyright' => '© ' . date('Y') . ' ' . $name . '. All rights reserved.',
                ],
            ],
            // Pages
            [
                'type' => 'about', 'title' => 'About Us', 'slug' => 'about',
                'content' => [
                    'title' => 'About',
                    'subtitle' => "We're building the future of business communication — one AI-powered reply at a time.",
                    'story' => "{$name} was born from a simple frustration: businesses spend too much time managing scattered inboxes across email, WhatsApp, Slack, and SMS. We built a unified platform that brings every conversation into one place, with AI that actually understands your business and drafts replies in your voice.",
                    'mission' => "Our mission is to help every team — from solo founders to enterprise organizations — communicate faster, smarter, and more personally at scale. We believe AI should amplify human connection, not replace it.",
                    'stats' => [
                        ['value' => '10K+', 'label' => 'Active Teams'],
                        ['value' => '50M+', 'label' => 'Emails Processed'],
                        ['value' => '99.9%', 'label' => 'Uptime'],
                        ['value' => '24/7', 'label' => 'AI Support'],
                    ],
                ],
            ],
            [
                'type' => 'why_us', 'title' => 'Why Us', 'slug' => 'why-us',
                'content' => [
                    'title' => 'Why Choose',
                    'subtitle' => "Here's what sets us apart from every other email tool on the market.",
                    'reasons' => [
                        ['title' => 'Self-Hosted & Secure', 'desc' => 'Your data stays on your server. No third-party cloud dependency. Full control over your communication data.'],
                        ['title' => 'AI That Learns Your Voice', 'desc' => 'Train the AI on your knowledge base, past replies, and FAQs. It drafts responses that sound like you, not a robot.'],
                        ['title' => 'Truly Unified Inbox', 'desc' => 'Email, WhatsApp, SMS, Slack, Telegram, and live chat — all in one view with smart routing and assignment.'],
                        ['title' => 'No Recurring SaaS Fees', 'desc' => 'One-time purchase. Host on your own server. No monthly charges eating into your margins.'],
                        ['title' => 'Built for Teams', 'desc' => 'Workspaces, role-based access, team assignment, collision detection, and shared templates out of the box.'],
                        ['title' => 'Developer Friendly', 'desc' => 'REST API, webhooks, Zapier integration, and clean documentation. Build custom workflows on top of the platform.'],
                    ],
                ],
            ],
            // Landing sections
            [
                'type' => 'hero', 'title' => 'Hero Section', 'slug' => 'landing-hero',
                'content' => [
                    'badge' => 'Now with GPT-4o & Claude 4 Support',
                    'title_line1' => 'Achieve flawless email delivery',
                    'title_highlight' => 'AI-powered',
                    'title_line2' => 'automation.',
                    'subtitle' => 'Optimize email performance, manage multi-channel conversations, and scale your business communication with AI that actually knows your business.',
                    'cta_text' => 'Get Started Free',
                    'cta_url' => '/register',
                    'cta2_text' => 'See Features',
                    'cta2_url' => '#features',
                ],
            ],
            [
                'type' => 'features', 'title' => 'Features Section', 'slug' => 'landing-features',
                'content' => [
                    'badge' => 'Features',
                    'title' => 'Everything you need in one platform',
                    'subtitle' => 'Powerful tools for email, CRM, automation, and multi-channel communication.',
                    'items' => [
                        ['title' => 'Unified Inbox', 'desc' => 'Email, WhatsApp, SMS, Slack, and Telegram — all in one inbox.'],
                        ['title' => 'AI-Powered Replies', 'desc' => 'Context-aware AI drafts replies using your knowledge base.'],
                        ['title' => 'Email Campaigns', 'desc' => 'Drag-and-drop editor, A/B testing, drip sequences.'],
                        ['title' => 'CRM & Deals', 'desc' => 'Track contacts, manage deal pipelines, score leads.'],
                        ['title' => 'Workflow Automation', 'desc' => 'Visual no-code builder with triggers and conditions.'],
                        ['title' => 'Analytics Dashboard', 'desc' => 'Track open rates, clicks, bounces across all channels.'],
                    ],
                ],
            ],
            [
                'type' => 'testimonials', 'title' => 'Testimonials', 'slug' => 'landing-testimonials',
                'content' => ['title' => 'Loved by teams'],
            ],
            [
                'type' => 'faq', 'title' => 'FAQ Section', 'slug' => 'landing-faq',
                'content' => [
                    'title' => 'Frequently Asked Questions',
                    'items' => [
                        ['question' => "Is {$name} truly self-hosted?", 'answer' => 'Yes. Install on your own server. Your data never leaves your infrastructure.'],
                        ['question' => 'What AI providers are supported?', 'answer' => 'OpenAI (GPT-4o), Anthropic (Claude), Google (Gemini), and Mistral. Bring your own API key.'],
                        ['question' => 'Can I connect multiple email accounts?', 'answer' => 'Yes — unlimited Gmail, Outlook, and custom IMAP/SMTP accounts.'],
                        ['question' => 'Is there a free plan?', 'answer' => 'Yes. Free plan includes basic inbox, limited contacts, and essential features.'],
                        ['question' => 'How does the one-time payment work?', 'answer' => 'Pay once for the license and host on your server. No recurring SaaS fees.'],
                    ],
                ],
            ],
            [
                'type' => 'cta', 'title' => 'CTA Section', 'slug' => 'landing-cta',
                'content' => [
                    'badge' => '2,000+ teams already onboard',
                    'title' => 'Your inbox is waiting to get smarter.',
                    'subtitle' => 'Stop drowning in emails. Let AI handle the replies, automate the workflows, and grow your revenue on autopilot.',
                    'cta_text' => 'Start Free — No Credit Card',
                    'cta_url' => '/register',
                    'note' => 'No credit card required. Setup in minutes.',
                ],
            ],
            // Legal pages
            [
                'type' => 'terms', 'title' => 'Terms of Service', 'slug' => 'terms',
                'content' => [
                    'last_updated' => 'January 1, 2026',
                    'sections' => [
                        ['title' => 'Acceptance of Terms', 'content' => "By accessing or using {$name}, you agree to be bound by these Terms of Service."],
                        ['title' => 'User Accounts', 'content' => 'You must create an account with accurate information. You are responsible for all activities under your account.'],
                        ['title' => 'Subscriptions & Billing', 'content' => 'Paid features require a subscription billed monthly or annually via Stripe. We may change pricing with 30 days notice.'],
                        ['title' => 'Data Processing', 'content' => 'The Service processes email and communication data you connect. AI features may send data to third-party providers solely to generate responses.'],
                        ['title' => 'Acceptable Use', 'content' => 'You agree not to send spam, upload unlawful content, attempt unauthorized access, reverse engineer the Service, or exceed usage limits.'],
                        ['title' => 'Intellectual Property', 'content' => "The Service is the property of {$name}. You retain ownership of your content. AI-generated content becomes yours once accepted."],
                        ['title' => 'Limitation of Liability', 'content' => "The Service is provided \"as is\". Total liability is limited to amounts paid in the preceding 12 months."],
                        ['title' => 'Termination', 'content' => 'You may terminate anytime. Data is retained 30 days after termination, then permanently deleted.'],
                        ['title' => 'Changes to Terms', 'content' => 'We may update these Terms with at least 14 days notice. Continued use constitutes acceptance.'],
                        ['title' => 'Governing Law', 'content' => 'These Terms are governed by the laws of the jurisdiction where the Company is incorporated.'],
                    ],
                ],
            ],
            [
                'type' => 'privacy', 'title' => 'Privacy Policy', 'slug' => 'privacy',
                'content' => [
                    'last_updated' => 'January 1, 2026',
                    'subtitle' => "How {$name} protects your data.",
                    'sections' => [
                        ['title' => 'Information We Collect', 'content' => 'We collect information you provide: name, email, billing info, and communication content.'],
                        ['title' => 'How We Use Your Information', 'content' => 'We use your data to provide the Service, process payments, and improve your experience.'],
                        ['title' => 'AI Data Processing', 'content' => 'AI features may send data to third-party providers. Your data is not used to train shared AI models.'],
                        ['title' => 'Data Sharing', 'content' => 'We do not sell your data. We share only with payment processors and AI providers.'],
                        ['title' => 'Data Security', 'content' => 'We use encryption at rest and in transit, secure authentication, and regular security audits.'],
                        ['title' => 'Your Rights (GDPR)', 'content' => 'EU/EEA users have rights to access, rectify, delete, restrict, port, and object.'],
                        ['title' => 'Data Retention', 'content' => 'Data is retained while your account is active. Removed within 30 days after deletion.'],
                        ['title' => 'Cookies', 'content' => 'We use essential cookies for authentication and analytics cookies for usage insights.'],
                    ],
                    'bottom_text' => 'For privacy inquiries, contact us through our Contact page.',
                ],
            ],
            [
                'type' => 'refund', 'title' => 'Refund Policy', 'slug' => 'refund-policy',
                'content' => [
                    'last_updated' => 'January 1, 2026',
                    'sections' => [
                        ['title' => 'Satisfaction Guarantee', 'content' => '14-day money-back guarantee on all new paid subscriptions.'],
                        ['title' => 'Eligibility', 'content' => 'Refunds available within 14 days of initial subscription, for first-time subscriptions only.'],
                        ['title' => 'Non-Refundable Items', 'content' => 'Renewals, add-ons, terminated accounts, and partial unused time are not refundable.'],
                        ['title' => 'How to Request', 'content' => 'Contact support with your account email. Processed within 5-10 business days.'],
                        ['title' => 'Cancellation', 'content' => 'Cancel anytime from account settings. Keep access until end of billing period.'],
                    ],
                ],
            ],
            [
                'type' => 'contact', 'title' => 'Contact Us', 'slug' => 'contact',
                'content' => [
                    'subtitle' => "Have a question? We'd love to hear from you.",
                    'support_email_label' => 'Email Support',
                    'response_time' => '24-48 hours',
                    'support_channels' => 'Email support, in-app help center, and priority support for paid plans.',
                ],
            ],
        ];

        foreach ($pages as $page) {
            $content = $page['content'];
            unset($page['content']);

            Page::updateOrCreate(
                ['type' => $page['type']],
                array_merge($page, [
                    'content' => $content,
                    'is_published' => true,
                ])
            );
        }
    }
}
