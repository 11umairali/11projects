<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CommercialDemoSeeder extends Seeder
{
    private int $workspaceId;
    private int $userId;
    private ?int $emailAccountId;
    private int $pipelineId;
    private array $stageIds;

    public function run(): void
    {
        $workspace = DB::table('workspaces')->first();
        $user = DB::table('users')->first();
        $emailAccount = DB::table('email_accounts')->where('workspace_id', $workspace->id)->first();
        $pipeline = DB::table('pipelines')->where('workspace_id', $workspace->id)->first();

        if (!$workspace || !$user) {
            $this->command->error('No workspace or user found. Please create them first.');
            return;
        }

        $this->workspaceId = $workspace->id;
        $this->userId = $user->id;
        $this->emailAccountId = $emailAccount?->id;

        // Create default pipeline and stages if none exist
        if (!$pipeline) {
            $pipelineId = DB::table('pipelines')->insertGetId([
                'workspace_id' => $this->workspaceId,
                'name' => 'Sales Pipeline',
                'is_default' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $stages = [
                ['name' => 'Lead', 'sort_order' => 1, 'color' => '#6366F1'],
                ['name' => 'Qualified', 'sort_order' => 2, 'color' => '#8B5CF6'],
                ['name' => 'Proposal', 'sort_order' => 3, 'color' => '#F59E0B'],
                ['name' => 'Negotiation', 'sort_order' => 4, 'color' => '#F97316'],
                ['name' => 'Won', 'sort_order' => 5, 'color' => '#10B981'],
                ['name' => 'Lost', 'sort_order' => 6, 'color' => '#EF4444'],
            ];

            foreach ($stages as $stage) {
                DB::table('deal_stages')->insert(array_merge($stage, [
                    'pipeline_id' => $pipelineId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]));
            }

            $this->pipelineId = $pipelineId;
        } else {
            $this->pipelineId = $pipeline->id;
        }

        $this->stageIds = DB::table('deal_stages')
            ->where('pipeline_id', $this->pipelineId)
            ->orderBy('sort_order')
            ->pluck('id')
            ->toArray();

        $this->seedCampaigns();
        $this->seedWorkflows();
        $this->seedDeals();
        $this->seedCannedResponses();

        $this->command->info('Commercial demo data seeded successfully.');
    }

    private function seedCampaigns(): void
    {
        if (DB::table('campaigns')->where('workspace_id', $this->workspaceId)->count() >= 50) {
            $this->command->warn('Campaigns already seeded (50+). Skipping.');
            return;
        }

        $now = Carbon::now();
        $campaigns = [];

        // ── 20 Sent campaigns ──────────────────────────────────────────
        $sentCampaigns = [
            ['Q1 Product Launch Announcement', 'Introducing our next-gen platform — see what\'s new', 'Big things are here. See the new features your team has been waiting for.'],
            ['Black Friday 2025 - Flash Sale', 'Up to 60% off — 24 hours only', 'Our biggest sale of the year starts now.'],
            ['Customer Onboarding Series - Week 1', 'Welcome aboard! Here\'s how to get started', 'Your journey starts here — let\'s make the most of it.'],
            ['Monthly Newsletter - March 2026', 'March updates: new integrations, tips & more', 'Your monthly roundup of everything new.'],
            ['Re-engagement: Inactive Users (90 days)', 'We miss you — here\'s what you\'ve been missing', 'A lot has changed since your last visit.'],
            ['Webinar Invitation: AI in Business', 'Join us March 28: How AI is transforming operations', 'Reserve your seat for our most popular webinar yet.'],
            ['Annual Customer Survey 2026', 'Your feedback shapes our roadmap — 2 min survey', 'Help us build what matters most to you.'],
            ['Feature Update: New Dashboard', 'Your dashboard just got a major upgrade', 'Real-time analytics, custom widgets, and more.'],
            ['Holiday Season Promotion', 'Season\'s greetings + a special offer inside', 'Happy holidays from our team to yours.'],
            ['VIP Customer Exclusive Offer', 'A thank-you offer — just for our top customers', 'You\'re one of our most valued partners.'],
            ['Case Study: How Acme Corp Scaled 10x', 'See how Acme Corp achieved 10x growth with us', 'Real results from a real customer.'],
            ['Product Roadmap 2026 Preview', 'Sneak peek: what\'s coming in 2026', 'Get an insider look at our product roadmap.'],
            ['End of Year Recap 2025', 'Your 2025 in review — the numbers that matter', 'Here\'s what you accomplished this year.'],
            ['Security Update: New 2FA Options', 'Important: enhanced security features now available', 'New ways to protect your account.'],
            ['Partner Program Launch', 'Earn up to 30% recurring commissions', 'Join our new partner program today.'],
            ['Customer Success Story: TechStart', 'How TechStart reduced churn by 47%', 'Data-driven strategies that actually work.'],
            ['API v3 Migration Guide', 'Action required: migrate to API v3 by April 30', 'Step-by-step guide to upgrade your integration.'],
            ['Spring Cleaning: Workspace Optimization Tips', '5 ways to streamline your workspace today', 'Quick wins for a more productive workflow.'],
            ['Referral Program: Give $50, Get $50', 'Share the love — earn credits for every referral', 'Your friends get a discount, you get rewarded.'],
            ['Data Privacy Day: Our Commitment', 'How we protect your data — transparency report', 'Your trust is our top priority.'],
        ];

        foreach ($sentCampaigns as $i => $c) {
            $sentCount = rand(800, 45000);
            $deliveredCount = (int)($sentCount * (rand(960, 995) / 1000));
            $openedCount = (int)($deliveredCount * (rand(150, 450) / 1000));
            $clickedCount = (int)($deliveredCount * (rand(20, 120) / 1000));
            $bouncedCount = $sentCount - $deliveredCount;
            $unsubscribedCount = (int)($deliveredCount * (rand(1, 8) / 1000));
            $sentAt = $now->copy()->subDays(rand(1, 180))->subHours(rand(0, 23));

            $campaigns[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'email_account_id' => $this->emailAccountId,
                'name' => $c[0],
                'type' => 'regular',
                'status' => 'sent',
                'subject' => $c[1],
                'body_html' => $this->campaignHtml($c[0], $c[1]),
                'body_json' => null,
                'preview_text' => $c[2],
                'audience_type' => ['all', 'segment', 'list'][array_rand(['all', 'segment', 'list'])],
                'audience_id' => null,
                'recipients_count' => $sentCount,
                'sent_count' => $sentCount,
                'delivered_count' => $deliveredCount,
                'opened_count' => $openedCount,
                'clicked_count' => $clickedCount,
                'bounced_count' => $bouncedCount,
                'unsubscribed_count' => $unsubscribedCount,
                'scheduled_at' => $sentAt->copy()->subHours(1),
                'sent_at' => $sentAt,
                'completed_at' => $sentAt->copy()->addMinutes(rand(15, 120)),
                'created_at' => $sentAt->copy()->subDays(rand(1, 5)),
                'updated_at' => $sentAt->copy()->addMinutes(rand(15, 120)),
            ];
        }

        // ── 10 Scheduled campaigns ─────────────────────────────────────
        $scheduledCampaigns = [
            ['Earth Day Sustainability Campaign', 'Our green initiatives + your exclusive eco-discount', 'Going green together — see how.'],
            ['Q2 Webinar Series Announcement', 'Register now: 4-part masterclass series', 'Level up your skills this quarter.'],
            ['Mother\'s Day Special', 'Celebrate Mom — special offers inside', 'Show appreciation with a thoughtful gift.'],
            ['New Feature: AI Writing Assistant', 'Write emails 5x faster with AI', 'Your new AI-powered writing companion.'],
            ['Summer Savings Event Preview', 'Early access: summer sale starts June 1', 'Get first dibs on our best deals.'],
            ['Customer Appreciation Week', 'Thank you! Enjoy a week of exclusive perks', 'You make everything we do possible.'],
            ['Industry Report: 2026 SaaS Trends', 'Download our free 2026 trends report', 'Data-backed insights for your strategy.'],
            ['Back-to-School for Business', 'Level up your team\'s skills this fall', 'Training resources, webinars, and more.'],
            ['Integration Spotlight: Salesforce', 'New: deeper Salesforce integration now live', 'Sync your CRM data seamlessly.'],
            ['Anniversary Sale — 5 Years Strong', 'Celebrating 5 years with 50% off', 'Half a decade. Half the price.'],
        ];

        foreach ($scheduledCampaigns as $c) {
            $scheduledAt = $now->copy()->addDays(rand(1, 60))->setHour(rand(8, 17))->setMinute(0)->setSecond(0);
            $campaigns[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'email_account_id' => $this->emailAccountId,
                'name' => $c[0],
                'type' => 'regular',
                'status' => 'scheduled',
                'subject' => $c[1],
                'body_html' => $this->campaignHtml($c[0], $c[1]),
                'body_json' => null,
                'preview_text' => $c[2],
                'audience_type' => ['all', 'segment', 'list'][array_rand(['all', 'segment', 'list'])],
                'audience_id' => null,
                'recipients_count' => rand(1000, 30000),
                'sent_count' => 0,
                'delivered_count' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
                'bounced_count' => 0,
                'unsubscribed_count' => 0,
                'scheduled_at' => $scheduledAt,
                'sent_at' => null,
                'completed_at' => null,
                'created_at' => $now->copy()->subDays(rand(1, 10)),
                'updated_at' => $now->copy()->subDays(rand(0, 3)),
            ];
        }

        // ── 10 Draft campaigns ─────────────────────────────────────────
        $draftCampaigns = [
            ['Product Demo Day Invitation', 'See our product in action — live demo', 'Book your slot for a personalized walkthrough.'],
            ['Content Marketing Playbook', 'Download: the ultimate content strategy guide', 'Proven tactics from top-performing teams.'],
            ['Developer Conference Early Bird', 'DevCon 2026: early bird tickets available', 'The biggest developer event of the year.'],
            ['Customer Health Score Launch', 'New: track customer health at a glance', 'Proactive insights for your success team.'],
            ['Year-End Planning Guide', 'Plan your 2027 strategy with our free template', 'Strategic planning made simple.'],
            ['Employee Spotlight Series', 'Meet the team behind the product', 'Real people, real passion.'],
            ['Compliance Update: GDPR 2.0', 'What the new regulations mean for you', 'Stay ahead of compliance changes.'],
            ['Beta Program Invitation', 'Be the first to try our newest features', 'Shape the future of our product.'],
            ['Win-Back: Cancelled Accounts', 'We\'d love to have you back — here\'s what\'s new', 'A lot has changed. Give us another look.'],
            ['Cross-Sell: Analytics Add-on', 'Unlock deeper insights with Advanced Analytics', 'See your data like never before.'],
        ];

        foreach ($draftCampaigns as $c) {
            $campaigns[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'email_account_id' => $this->emailAccountId,
                'name' => $c[0],
                'type' => ['regular', 'ab_test', 'drip'][array_rand(['regular', 'ab_test', 'drip'])],
                'status' => 'draft',
                'subject' => $c[1],
                'body_html' => $this->campaignHtml($c[0], $c[1]),
                'body_json' => null,
                'preview_text' => $c[2],
                'audience_type' => null,
                'audience_id' => null,
                'recipients_count' => 0,
                'sent_count' => 0,
                'delivered_count' => 0,
                'opened_count' => 0,
                'clicked_count' => 0,
                'bounced_count' => 0,
                'unsubscribed_count' => 0,
                'scheduled_at' => null,
                'sent_at' => null,
                'completed_at' => null,
                'created_at' => $now->copy()->subDays(rand(0, 14)),
                'updated_at' => $now->copy()->subDays(rand(0, 3)),
            ];
        }

        // ── 5 Sending campaigns ────────────────────────────────────────
        $sendingCampaigns = [
            ['Flash Sale: 48-Hour Countdown', '48 hours left — don\'t miss this deal', 'The clock is ticking on our biggest offer.'],
            ['Breaking: New Integration Partners', 'We just launched 12 new integrations', 'Connect your favorite tools instantly.'],
            ['Urgent: Account Security Advisory', 'Action required: update your security settings', 'We\'ve enhanced our security protocols.'],
            ['Limited Seats: Executive Workshop', 'Only 20 seats left — reserve yours now', 'An exclusive workshop for senior leaders.'],
            ['Live Now: Product Hunt Launch', 'We\'re live on Product Hunt — support us!', 'Help us reach #1 today.'],
        ];

        foreach ($sendingCampaigns as $c) {
            $totalRecipients = rand(5000, 40000);
            $sentSoFar = (int)($totalRecipients * (rand(20, 80) / 100));
            $deliveredCount = (int)($sentSoFar * (rand(960, 995) / 1000));
            $openedCount = (int)($deliveredCount * (rand(100, 300) / 1000));
            $clickedCount = (int)($deliveredCount * (rand(10, 60) / 1000));
            $bouncedCount = $sentSoFar - $deliveredCount;

            $campaigns[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'email_account_id' => $this->emailAccountId,
                'name' => $c[0],
                'type' => 'regular',
                'status' => 'sending',
                'subject' => $c[1],
                'body_html' => $this->campaignHtml($c[0], $c[1]),
                'body_json' => null,
                'preview_text' => $c[2],
                'audience_type' => 'all',
                'audience_id' => null,
                'recipients_count' => $totalRecipients,
                'sent_count' => $sentSoFar,
                'delivered_count' => $deliveredCount,
                'opened_count' => $openedCount,
                'clicked_count' => $clickedCount,
                'bounced_count' => $bouncedCount,
                'unsubscribed_count' => (int)($deliveredCount * (rand(1, 5) / 1000)),
                'scheduled_at' => $now->copy()->subMinutes(rand(10, 120)),
                'sent_at' => $now->copy()->subMinutes(rand(5, 60)),
                'completed_at' => null,
                'created_at' => $now->copy()->subDays(rand(1, 7)),
                'updated_at' => $now,
            ];
        }

        // ── 5 Paused campaigns ─────────────────────────────────────────
        $pausedCampaigns = [
            ['A/B Test: Subject Line Optimization', 'Version A: Boost your ROI by 40% | Version B pending', 'Testing subject line performance.'],
            ['Regional Campaign: EMEA Launch', 'Expanding to Europe — exclusive early access', 'We\'re going international.'],
            ['Seasonal Drip: Winter Onboarding', 'Getting started this winter? Here\'s your guide', 'Warm up with our onboarding series.'],
            ['Re-targeting: Free Trial Expiring', 'Your free trial ends in 3 days', 'Don\'t lose access to your work.'],
            ['Loyalty Program Announcement', 'Introducing Rewards+ — earn points on every action', 'Your loyalty deserves recognition.'],
        ];

        foreach ($pausedCampaigns as $c) {
            $totalRecipients = rand(3000, 25000);
            $sentSoFar = (int)($totalRecipients * (rand(10, 50) / 100));
            $deliveredCount = (int)($sentSoFar * (rand(960, 995) / 1000));

            $campaigns[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'email_account_id' => $this->emailAccountId,
                'name' => $c[0],
                'type' => $c[0] === 'A/B Test: Subject Line Optimization' ? 'ab_test' : 'regular',
                'status' => 'paused',
                'subject' => $c[1],
                'body_html' => $this->campaignHtml($c[0], $c[1]),
                'body_json' => null,
                'preview_text' => $c[2],
                'audience_type' => 'segment',
                'audience_id' => null,
                'recipients_count' => $totalRecipients,
                'sent_count' => $sentSoFar,
                'delivered_count' => $deliveredCount,
                'opened_count' => (int)($deliveredCount * (rand(100, 250) / 1000)),
                'clicked_count' => (int)($deliveredCount * (rand(10, 50) / 1000)),
                'bounced_count' => $sentSoFar - $deliveredCount,
                'unsubscribed_count' => (int)($deliveredCount * (rand(1, 4) / 1000)),
                'scheduled_at' => $now->copy()->subDays(rand(1, 10)),
                'sent_at' => $now->copy()->subDays(rand(1, 5)),
                'completed_at' => null,
                'created_at' => $now->copy()->subDays(rand(5, 20)),
                'updated_at' => $now->copy()->subDays(rand(0, 2)),
            ];
        }

        DB::table('campaigns')->insert($campaigns);
        $this->command->info('Seeded ' . count($campaigns) . ' campaigns.');
    }

    private function campaignHtml(string $name, string $subject): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f4f7">
<tr><td align="center" style="padding:40px 0">
<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.06)">
<tr><td style="background:linear-gradient(135deg,#6366f1,#8b5cf6);padding:32px 40px;text-align:center"><h1 style="margin:0;color:#ffffff;font-size:24px;font-weight:700">{$name}</h1></td></tr>
<tr><td style="padding:40px"><h2 style="margin:0 0 16px;color:#1a1a2e;font-size:20px">{$subject}</h2><p style="margin:0 0 24px;color:#4a5568;font-size:16px;line-height:1.6">We have exciting updates to share with you. Our team has been working hard to deliver the best experience possible, and we are thrilled to bring you the latest improvements.</p><p style="margin:0 0 32px;color:#4a5568;font-size:16px;line-height:1.6">Click below to learn more about what is new and how it benefits your workflow.</p><table role="presentation" cellspacing="0" cellpadding="0"><tr><td style="border-radius:6px;background:#6366f1"><a href="#" style="display:inline-block;padding:14px 32px;color:#ffffff;text-decoration:none;font-size:16px;font-weight:600">Learn More</a></td></tr></table></td></tr>
<tr><td style="padding:24px 40px;background:#f8fafc;border-top:1px solid #e2e8f0;text-align:center"><p style="margin:0;color:#94a3b8;font-size:13px">You are receiving this because you are a valued subscriber.<br><a href="#" style="color:#6366f1;text-decoration:underline">Unsubscribe</a> | <a href="#" style="color:#6366f1;text-decoration:underline">Manage Preferences</a></p></td></tr>
</table>
</td></tr></table>
</body>
</html>
HTML;
    }

    private function seedWorkflows(): void
    {
        if (DB::table('workflows')->where('workspace_id', $this->workspaceId)->count() >= 50) {
            $this->command->warn('Workflows already seeded (50+). Skipping.');
            return;
        }

        $now = Carbon::now();

        $workflowDefs = [
            // Active workflows (20)
            ['Welcome Email Series', 'Sends a 5-part welcome series to new subscribers over 14 days', 'active', rand(1200, 15000)],
            ['Abandoned Cart Recovery', 'Triggers 3-email sequence when a user abandons their cart', 'active', rand(800, 9500)],
            ['Lead Scoring Automation', 'Automatically scores leads based on email engagement and page visits', 'active', rand(3000, 25000)],
            ['Post-Purchase Follow-up', 'Sends thank-you email + review request 7 days after purchase', 'active', rand(2000, 18000)],
            ['Subscription Renewal Reminder', 'Alerts customers 30, 14, and 3 days before renewal', 'active', rand(1500, 12000)],
            ['Churn Prevention Alert', 'Flags at-risk accounts and triggers retention sequence', 'active', rand(500, 6000)],
            ['NPS Survey Trigger', 'Sends Net Promoter Score survey 30 days after onboarding', 'active', rand(1000, 8000)],
            ['Meeting Follow-up Sequence', 'Sends summary and next steps after a scheduled meeting', 'active', rand(300, 4000)],
            ['Trial-to-Paid Conversion', 'Nurture sequence during 14-day free trial with feature highlights', 'active', rand(2500, 20000)],
            ['VIP Customer Upgrade Path', 'Identifies high-value users and offers premium tier upgrade', 'active', rand(400, 5000)],
            ['Customer Birthday Greeting', 'Sends personalized birthday email with discount code', 'active', rand(800, 6000)],
            ['Feedback Loop Automation', 'Collects feedback after support ticket resolution', 'active', rand(1500, 10000)],
            ['Webinar Registration Nurture', 'Pre-webinar reminders and post-webinar replay delivery', 'active', rand(700, 5500)],
            ['Invoice Overdue Escalation', 'Progressive reminder emails for overdue invoices at 3, 7, 14 days', 'active', rand(300, 3000)],
            ['Content Recommendation Engine', 'Sends personalized content based on reading history', 'active', rand(5000, 30000)],
            ['Re-engagement Drip (60 days)', 'Targets users inactive for 60+ days with win-back offers', 'active', rand(1000, 8000)],
            ['Product Adoption Milestones', 'Celebrates user milestones: first project, 100th email, etc.', 'active', rand(2000, 15000)],
            ['Referral Program Automation', 'Triggers referral invite after positive NPS response', 'active', rand(600, 4000)],
            ['Seasonal Campaign Scheduler', 'Auto-launches holiday campaigns based on calendar events', 'active', rand(200, 2000)],
            ['Data Enrichment Pipeline', 'Enriches contact data from third-party sources on new sign-up', 'active', rand(3000, 20000)],

            // Inactive workflows (15)
            ['Legacy Welcome Flow (v1)', 'Original welcome series — replaced by v2', 'paused', rand(10000, 50000)],
            ['Black Friday Auto-Responder', 'Seasonal — activates only during BFCM period', 'paused', rand(5000, 20000)],
            ['Old Lead Qualification', 'Deprecated — replaced by AI-based scoring', 'paused', rand(8000, 30000)],
            ['Support Ticket Auto-Assign', 'Paused for reconfiguration of routing rules', 'paused', rand(2000, 15000)],
            ['Holiday Out-of-Office Detector', 'Detects OOO replies and pauses sequences', 'paused', rand(500, 3000)],
            ['Compliance Consent Renewal', 'Annual GDPR consent refresh — runs in January', 'paused', rand(1000, 8000)],
            ['Partner Onboarding v1', 'First version of partner onboarding — needs update', 'paused', rand(300, 2000)],
            ['Exit Survey Automation', 'Triggers survey when subscription is cancelled', 'paused', rand(400, 3500)],
            ['Social Proof Collector', 'Requests testimonials from happy customers', 'paused', rand(200, 1500)],
            ['A/B Subject Line Tester', 'Automated subject line testing on small segments', 'paused', rand(600, 4000)],
            ['Contract Expiry Notifier', 'Alerts account managers 90 days before contract end', 'paused', rand(100, 1000)],
            ['Event Attendance Follow-up', 'Post-event email series for conference attendees', 'paused', rand(300, 2500)],
            ['Winback: Lost Deals', 'Re-engages contacts from lost deals after 90 days', 'paused', rand(150, 1200)],
            ['Quarterly Business Review Prep', 'Sends QBR data package to customer success team', 'paused', rand(50, 500)],
            ['Upsell: Feature Usage Trigger', 'Suggests upgrade when usage hits plan limits', 'paused', rand(800, 5000)],

            // Draft workflows (15)
            ['AI Sentiment Response', 'Analyzes incoming email sentiment and routes accordingly', 'draft', 0],
            ['Multi-Channel Outreach', 'Combines email, SMS, and in-app messages in one flow', 'draft', 0],
            ['Dynamic Pricing Notification', 'Alerts users when pricing on watched items changes', 'draft', 0],
            ['Competitor Mention Tracker', 'Monitors for competitor mentions and alerts sales team', 'draft', 0],
            ['Predictive Churn Model', 'Uses ML scores to trigger proactive outreach', 'draft', 0],
            ['Account-Based Marketing Flow', 'Targets decision makers at high-value accounts', 'draft', 0],
            ['Onboarding Health Check', 'Weekly check on onboarding progress with intervention triggers', 'draft', 0],
            ['Customer Expansion Revenue', 'Identifies expansion opportunities from product usage data', 'draft', 0],
            ['SLA Breach Alerting', 'Escalates when support response time exceeds SLA thresholds', 'draft', 0],
            ['Automated Report Delivery', 'Generates and emails weekly performance reports', 'draft', 0],
            ['Lead Source Attribution', 'Tags and routes leads based on acquisition channel', 'draft', 0],
            ['Product Feedback Loop', 'Routes feature requests from email to product board', 'draft', 0],
            ['Smart Unsubscribe Handler', 'Offers preference center instead of full unsubscribe', 'draft', 0],
            ['Win/Loss Analysis Collector', 'Gathers deal outcome data for sales coaching', 'draft', 0],
            ['Capacity Planning Alert', 'Notifies ops when email sending volume nears plan limits', 'draft', 0],
        ];

        $workflows = [];
        foreach ($workflowDefs as $w) {
            $workflows[] = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'created_by' => $this->userId,
                'name' => $w[0],
                'description' => $w[1],
                'status' => $w[2],
                'canvas_data' => json_encode($this->generateCanvasData($w[0], $w[2])),
                'version' => $w[2] === 'draft' ? 1 : rand(1, 5),
                'executions_count' => $w[3],
                'webhook_token' => Str::random(64),
                'created_at' => $now->copy()->subDays(rand(10, 300)),
                'updated_at' => $now->copy()->subDays(rand(0, 30)),
            ];
        }

        DB::table('workflows')->insert($workflows);

        // Create actual workflow_nodes and workflow_edges for execution
        $savedWorkflows = DB::table('workflows')->where('workspace_id', $this->workspaceId)->get();
        foreach ($savedWorkflows as $wf) {
            $canvas = json_decode($wf->canvas_data, true);
            if (empty($canvas['nodes'])) continue;

            $nodeIdMap = []; // canvas id → db id
            foreach ($canvas['nodes'] as $node) {
                $dbId = DB::table('workflow_nodes')->insertGetId([
                    'workflow_id' => $wf->id,
                    'type' => $node['type'],
                    'subtype' => $node['data']['subtype'] ?? $node['type'],
                    'config' => json_encode($node['data'] ?? []),
                    'position_x' => $node['position']['x'] ?? 0,
                    'position_y' => $node['position']['y'] ?? 0,
                    'created_at' => $wf->created_at,
                    'updated_at' => $wf->updated_at,
                ]);
                $nodeIdMap[$node['id']] = $dbId;
            }

            foreach ($canvas['edges'] ?? [] as $edge) {
                $fromId = $nodeIdMap[$edge['source']] ?? null;
                $toId = $nodeIdMap[$edge['target']] ?? null;
                if ($fromId && $toId) {
                    DB::table('workflow_edges')->insert([
                        'workflow_id' => $wf->id,
                        'from_node_id' => $fromId,
                        'to_node_id' => $toId,
                        'label' => $edge['label'] ?? 'default',
                        'created_at' => $wf->created_at,
                        'updated_at' => $wf->updated_at,
                    ]);
                }
            }
        }

        $this->command->info('Seeded ' . count($workflows) . ' workflows with nodes & edges.');
    }

    private function generateCanvasData(string $name, string $status): array
    {
        // Generate a realistic visual builder canvas with trigger + actions
        $nodes = [
            ['id' => 'trigger-1', 'type' => 'trigger', 'position' => ['x' => 250, 'y' => 50], 'data' => ['label' => 'When triggered', 'subtype' => 'email_received']],
            ['id' => 'action-1', 'type' => 'action', 'position' => ['x' => 250, 'y' => 200], 'data' => ['label' => 'Send Email', 'subtype' => 'send_email']],
            ['id' => 'condition-1', 'type' => 'condition', 'position' => ['x' => 250, 'y' => 350], 'data' => ['label' => 'Email Opened?', 'subtype' => 'if_else']],
            ['id' => 'action-2', 'type' => 'action', 'position' => ['x' => 100, 'y' => 500], 'data' => ['label' => 'Send Follow-up', 'subtype' => 'send_email']],
            ['id' => 'action-3', 'type' => 'action', 'position' => ['x' => 400, 'y' => 500], 'data' => ['label' => 'Wait 3 days', 'subtype' => 'delay']],
        ];

        $edges = [
            ['source' => 'trigger-1', 'target' => 'action-1'],
            ['source' => 'action-1', 'target' => 'condition-1'],
            ['source' => 'condition-1', 'target' => 'action-2', 'label' => 'Yes'],
            ['source' => 'condition-1', 'target' => 'action-3', 'label' => 'No'],
        ];

        return ['nodes' => $nodes, 'edges' => $edges, 'viewport' => ['x' => 0, 'y' => 0, 'zoom' => 1]];
    }

    private function seedDeals(): void
    {
        if (DB::table('deals')->where('workspace_id', $this->workspaceId)->count() >= 30) {
            $this->command->warn('Deals already seeded (30+). Skipping.');
            return;
        }

        $now = Carbon::now();

        // Get or create a contact to use
        $contactId = DB::table('contacts')->where('workspace_id', $this->workspaceId)->value('id');
        if (!$contactId) {
            $contactId = DB::table('contacts')->insertGetId([
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'email' => 'demo-contact@example.com',
                'first_name' => 'Demo',
                'last_name' => 'Contact',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $dealDefs = [
            // Open deals across stages
            ['Acme Corp - Enterprise License', 125000, 'open', 0],
            ['TechStart SaaS Migration', 45000, 'open', 1],
            ['GlobalFin Data Platform', 280000, 'open', 2],
            ['MediCare Solutions Upgrade', 67000, 'open', 0],
            ['RetailMax POS Integration', 32000, 'open', 1],
            ['EduTech Learning Suite', 89000, 'open', 3],
            ['CloudNine Infrastructure Deal', 175000, 'open', 2],
            ['Pinnacle Consulting - Annual Contract', 54000, 'open', 0],
            ['SmartLogistics Fleet Platform', 210000, 'open', 3],
            ['UrbanDev Property Management', 38000, 'open', 1],
            ['Nexus Gaming Analytics', 95000, 'open', 2],
            ['Harmony Health - Patient Portal', 150000, 'open', 0],
            ['DataVault Compliance Suite', 72000, 'open', 3],
            ['AgriSense IoT Platform', 118000, 'open', 1],
            ['BrightPath HR Automation', 43000, 'open', 2],

            // Won deals (stage index 4 = Won)
            ['Stellar Dynamics - Full Platform', 340000, 'won', 4],
            ['NovaSync Communications', 88000, 'won', 4],
            ['BluePeak Analytics Renewal', 56000, 'won', 4],
            ['CrystalClear Video Conferencing', 125000, 'won', 4],
            ['Atlas Shipping Integration', 190000, 'won', 4],
            ['Forge Industries - Custom Build', 420000, 'won', 4],
            ['Meridian Hotels Group', 275000, 'won', 4],
            ['OptiFlow Supply Chain', 160000, 'won', 4],

            // Lost deals
            ['Zenith Corp - Budget Rejected', 95000, 'lost', 2],
            ['FrostByte Cloud - Chose Competitor', 200000, 'lost', 3],
            ['QuickServe - Timing Issue', 28000, 'lost', 1],
            ['SilverLine Insurance - Regulatory Hold', 310000, 'lost', 3],
            ['CityPulse Media - Restructuring', 65000, 'lost', 1],
            ['TerraFirm Construction - Budget Cut', 145000, 'lost', 2],
            ['Elevate Fitness - Deprioritized', 18000, 'lost', 0],
        ];

        $lostReasons = [
            'Budget constraints — project deprioritized for this fiscal year',
            'Chose competitor offering — pricing was the deciding factor',
            'Timing — prospect postponed decision to next quarter',
            'Regulatory hold — compliance review required before procurement',
            'Internal restructuring — key stakeholder left the company',
            'Budget reallocated to different initiative',
            'Feature gap — required functionality not on our near-term roadmap',
        ];

        $deals = [];
        foreach ($dealDefs as $i => $d) {
            $stageId = $this->stageIds[$d[3]] ?? $this->stageIds[0];
            $createdAt = $now->copy()->subDays(rand(5, 180));

            $deal = [
                'uuid' => Str::uuid()->toString(),
                'workspace_id' => $this->workspaceId,
                'contact_id' => $contactId,
                'pipeline_id' => $this->pipelineId,
                'deal_stage_id' => $stageId,
                'assigned_to' => $this->userId,
                'title' => $d[0],
                'value' => $d[1],
                'currency' => 'USD',
                'expected_close_date' => $d[2] === 'open'
                    ? $now->copy()->addDays(rand(7, 90))->toDateString()
                    : $createdAt->copy()->addDays(rand(14, 60))->toDateString(),
                'status' => $d[2],
                'won_at' => $d[2] === 'won' ? $createdAt->copy()->addDays(rand(10, 45)) : null,
                'lost_at' => $d[2] === 'lost' ? $createdAt->copy()->addDays(rand(10, 45)) : null,
                'lost_reason' => $d[2] === 'lost' ? $lostReasons[array_rand($lostReasons)] : null,
                'custom_fields' => json_encode(['source' => ['referral', 'inbound', 'outbound', 'partner', 'conference'][array_rand(['referral', 'inbound', 'outbound', 'partner', 'conference'])]]),
                'notes' => null,
                'created_at' => $createdAt,
                'updated_at' => $now->copy()->subDays(rand(0, 14)),
            ];

            $deals[] = $deal;
        }

        DB::table('deals')->insert($deals);
        $this->command->info('Seeded ' . count($deals) . ' deals.');
    }

    private function seedCannedResponses(): void
    {
        if (DB::table('canned_responses')->where('workspace_id', $this->workspaceId)->count() >= 20) {
            $this->command->warn('Canned responses already seeded (20+). Skipping.');
            return;
        }

        $now = Carbon::now();

        $responses = [
            [
                'title' => 'Greeting - New Customer',
                'shortcut' => '/greet-new',
                'category' => 'general',
                'usage_count' => rand(50, 400),
                'content' => "Hi {{contact.first_name}},\n\nThank you for choosing our platform! We're excited to have you on board.\n\nIf you have any questions about getting started, don't hesitate to reach out. We're here to help you every step of the way.\n\nBest regards,\n{{agent.name}}",
            ],
            [
                'title' => 'Pricing Information Request',
                'shortcut' => '/pricing',
                'category' => 'sales',
                'usage_count' => rand(80, 500),
                'content' => "Hi {{contact.first_name}},\n\nThank you for your interest in our pricing plans.\n\nWe offer three tiers designed to scale with your business:\n\n- Starter: \$49/mo — ideal for small teams (up to 5 users)\n- Professional: \$149/mo — for growing businesses (up to 25 users)\n- Enterprise: Custom pricing — unlimited users, dedicated support, SLA\n\nAll plans include a 14-day free trial with no credit card required. I'd be happy to schedule a quick call to help you find the best fit.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Technical Support - Reset Password',
                'shortcut' => '/reset-pw',
                'category' => 'support',
                'usage_count' => rand(200, 800),
                'content' => "Hi {{contact.first_name}},\n\nI understand you're having trouble accessing your account. Here's how to reset your password:\n\n1. Go to our login page and click \"Forgot Password\"\n2. Enter the email address associated with your account\n3. Check your inbox for a reset link (also check spam/junk)\n4. Click the link and create a new password (minimum 8 characters)\n\nThe reset link expires after 60 minutes. If you continue to experience issues, let me know and I can assist further.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Refund Policy',
                'shortcut' => '/refund',
                'category' => 'billing',
                'usage_count' => rand(30, 200),
                'content' => "Hi {{contact.first_name}},\n\nThank you for reaching out regarding our refund policy.\n\nWe offer a 30-day money-back guarantee on all annual plans. For monthly plans, you can cancel at any time and your subscription will remain active until the end of the current billing period.\n\nTo request a refund, please provide your account email and the reason for your request, and I'll process it within 3-5 business days.\n\nPlease let me know if you have any other questions.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Meeting Scheduling',
                'shortcut' => '/schedule',
                'category' => 'sales',
                'usage_count' => rand(100, 600),
                'content' => "Hi {{contact.first_name}},\n\nI'd love to schedule a call to discuss your needs in more detail.\n\nYou can book a time that works for you directly on my calendar: [Calendar Link]\n\nAlternatively, here are some available slots this week:\n- Tuesday 2:00 PM - 2:30 PM EST\n- Wednesday 10:00 AM - 10:30 AM EST\n- Thursday 3:00 PM - 3:30 PM EST\n\nLooking forward to connecting!\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Feature Request Acknowledgment',
                'shortcut' => '/feat-ack',
                'category' => 'support',
                'usage_count' => rand(40, 250),
                'content' => "Hi {{contact.first_name}},\n\nThank you for sharing your feature suggestion! We genuinely appreciate customer feedback — it directly shapes our product roadmap.\n\nI've logged your request and shared it with our product team. While I can't guarantee a timeline, I want you to know that we review all suggestions during our monthly planning sessions.\n\nI'll make sure to follow up if this feature makes it into an upcoming release.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Bug Report Confirmation',
                'shortcut' => '/bug-ack',
                'category' => 'support',
                'usage_count' => rand(60, 350),
                'content' => "Hi {{contact.first_name}},\n\nThank you for reporting this issue. I've been able to reproduce the behavior you described and have escalated it to our engineering team.\n\nHere's what you can expect:\n- Our team will investigate and prioritize the fix\n- We'll notify you once the fix is deployed\n- If we need additional information, we'll reach out\n\nTicket reference: #{{ticket.id}}\n\nIn the meantime, here's a workaround: [if applicable]\n\nWe appreciate your patience.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Onboarding Welcome',
                'shortcut' => '/onboard',
                'category' => 'general',
                'usage_count' => rand(70, 300),
                'content' => "Hi {{contact.first_name}},\n\nWelcome to the team! I'm your dedicated success manager and I'll be guiding you through the onboarding process.\n\nHere's what we'll cover over the next two weeks:\n\n1. Account Setup & Configuration (Day 1-2)\n2. Team Invitation & Permissions (Day 3)\n3. Integration Setup (Day 4-5)\n4. Workflow Automation Training (Week 2)\n5. Go-Live Checklist & Review (End of Week 2)\n\nI've attached our Getting Started guide. Let's schedule a 30-minute kickoff call at your earliest convenience.\n\nExcited to get started!\n\n{{agent.name}}",
            ],
            [
                'title' => 'Invoice / Billing Question',
                'shortcut' => '/invoice',
                'category' => 'billing',
                'usage_count' => rand(50, 280),
                'content' => "Hi {{contact.first_name}},\n\nThank you for reaching out about your billing question.\n\nI've reviewed your account and here's what I found:\n- Current Plan: [Plan Name]\n- Billing Cycle: [Monthly/Annual]\n- Next Invoice Date: [Date]\n- Amount Due: [Amount]\n\nYou can also view and download all invoices from your account dashboard under Settings > Billing > Invoices.\n\nIf you notice any discrepancy or have additional questions, I'm happy to help.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Out of Office / Away',
                'shortcut' => '/ooo',
                'category' => 'general',
                'usage_count' => rand(20, 150),
                'content' => "Hi {{contact.first_name}},\n\nThank you for your email. I'm currently out of the office and will return on [Date].\n\nFor urgent matters, please contact our support team at support@company.com or reply to this email and a colleague will assist you.\n\nI'll follow up with you as soon as I'm back.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Integration Support',
                'shortcut' => '/integrate',
                'category' => 'support',
                'usage_count' => rand(40, 220),
                'content' => "Hi {{contact.first_name}},\n\nI'd be happy to help you set up your integration.\n\nHere's a quick overview of the steps:\n\n1. Navigate to Settings > Integrations in your dashboard\n2. Find [Integration Name] and click \"Connect\"\n3. Authorize the connection using your [Service] credentials\n4. Configure the sync settings (we recommend starting with a one-way sync)\n5. Run a test sync to verify the connection\n\nOur detailed integration guide is available here: [docs link]\n\nIf you run into any issues, please share a screenshot of the error and I'll troubleshoot with you.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Upgrade Recommendation',
                'shortcut' => '/upgrade',
                'category' => 'sales',
                'usage_count' => rand(30, 180),
                'content' => "Hi {{contact.first_name}},\n\nI noticed your team has been growing — congratulations!\n\nBased on your current usage, you might benefit from upgrading to our Professional plan. Here's what you'd gain:\n\n- Increased user seats (from 5 to 25)\n- Advanced analytics and reporting\n- Priority support with 4-hour SLA\n- Custom workflow automation\n- API access with higher rate limits\n\nUpgrading is seamless — your data and settings carry over instantly. I can also apply a 20% loyalty discount for the first year.\n\nWant to hop on a quick call to discuss?\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Data Export Request',
                'shortcut' => '/export',
                'category' => 'support',
                'usage_count' => rand(15, 120),
                'content' => "Hi {{contact.first_name}},\n\nAbsolutely — you can export your data at any time. Here's how:\n\n1. Go to Settings > Data Management > Export\n2. Select the data types you want to export (contacts, emails, reports, etc.)\n3. Choose the format (CSV or JSON)\n4. Click \"Generate Export\"\n\nThe export will be processed in the background and you'll receive a download link via email within a few minutes, depending on the data volume.\n\nFor larger exports (100k+ records), we recommend scheduling during off-peak hours.\n\nLet me know if you need anything else.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Security / Compliance Inquiry',
                'shortcut' => '/security',
                'category' => 'general',
                'usage_count' => rand(20, 100),
                'content' => "Hi {{contact.first_name}},\n\nGreat question — security is a top priority for us. Here's a quick overview:\n\n- SOC 2 Type II certified\n- GDPR and CCPA compliant\n- Data encrypted at rest (AES-256) and in transit (TLS 1.3)\n- SSO support (SAML 2.0, OAuth 2.0)\n- Two-factor authentication available\n- 99.99% uptime SLA on Enterprise plans\n- Annual third-party penetration testing\n\nI can share our full security whitepaper and compliance documentation. We also offer a security review call with our CISO for Enterprise prospects.\n\nWould any of these be helpful?\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Cancellation - Save Attempt',
                'shortcut' => '/cancel-save',
                'category' => 'billing',
                'usage_count' => rand(25, 150),
                'content' => "Hi {{contact.first_name}},\n\nI'm sorry to hear you're considering canceling. Before we process your request, I'd love to understand what's driving this decision — your feedback helps us improve.\n\nCommon concerns we can often address:\n- Underutilized features — I can set up a training session\n- Pricing — we have flexible options that might work better\n- Missing functionality — our roadmap may address your needs\n- Performance issues — our engineering team can investigate\n\nWe also offer a plan pause option if you just need a temporary break (up to 3 months, no charge).\n\nWould you be open to a quick 10-minute call? I'd hate for you to lose your data and configurations.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Testimonial / Case Study Request',
                'shortcut' => '/testimonial',
                'category' => 'general',
                'usage_count' => rand(10, 80),
                'content' => "Hi {{contact.first_name}},\n\nIt's wonderful to hear about the results you've been achieving with our platform! We'd love to feature your story.\n\nParticipating in a case study typically involves:\n- A 20-minute interview about your experience\n- Review and approval of the final draft before publishing\n- Promotion across our marketing channels (optional)\n\nAs a thank-you, case study participants receive a 15% discount on their next renewal and early access to our beta features.\n\nWould you be interested? No pressure at all — just let me know.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'API Rate Limit Exceeded',
                'shortcut' => '/rate-limit',
                'category' => 'support',
                'usage_count' => rand(30, 200),
                'content' => "Hi {{contact.first_name}},\n\nIt looks like your application is hitting our API rate limits. Here's some context:\n\nYour current plan allows:\n- 1,000 requests per minute\n- 50,000 requests per hour\n\nTo resolve this, you can:\n1. Implement exponential backoff in your integration\n2. Use webhooks instead of polling for real-time data\n3. Batch requests where possible (our bulk endpoints support up to 100 items per call)\n4. Upgrade to a higher tier for increased limits\n\nI can also temporarily increase your rate limit while you optimize your integration. Just say the word.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Partnership Inquiry Response',
                'shortcut' => '/partner',
                'category' => 'sales',
                'usage_count' => rand(15, 100),
                'content' => "Hi {{contact.first_name}},\n\nThank you for your interest in our partnership program!\n\nWe offer several partnership models:\n\n- Referral Partner: Earn 20% recurring commission for each referral\n- Technology Partner: Integrate your product with ours via our API\n- Solutions Partner: Resell and implement our platform for your clients\n- Agency Partner: White-label our platform under your brand\n\nEach tier includes co-marketing support, dedicated partner manager, and access to our partner portal.\n\nI'd love to set up an introductory call to discuss which model aligns best with your business goals.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Account Suspension Notice',
                'shortcut' => '/suspended',
                'category' => 'billing',
                'usage_count' => rand(10, 60),
                'content' => "Hi {{contact.first_name}},\n\nI'm reaching out regarding your account status. Your account has been temporarily suspended due to [reason: failed payment / TOS violation / security concern].\n\nTo restore full access:\n1. [Update your payment method in Settings > Billing]\n2. [Review our Terms of Service: link]\n3. [Verify your identity by replying to this email]\n\nYour data is safe and will not be deleted. Accounts are preserved for 90 days after suspension.\n\nPlease respond to this email or call us at [phone] if you need immediate assistance.\n\nBest,\n{{agent.name}}",
            ],
            [
                'title' => 'Thank You - Issue Resolved',
                'shortcut' => '/resolved',
                'category' => 'support',
                'usage_count' => rand(100, 500),
                'content' => "Hi {{contact.first_name}},\n\nGreat news — the issue you reported has been resolved.\n\nHere's a summary of what was done:\n- Issue: [brief description]\n- Root cause: [explanation]\n- Resolution: [what was fixed]\n\nPlease verify on your end and let me know if everything looks good. If the issue persists, just reopen this ticket by replying here — no need to create a new one.\n\nThank you for your patience and for bringing this to our attention.\n\nBest,\n{{agent.name}}",
            ],
        ];

        $rows = [];
        foreach ($responses as $r) {
            $rows[] = [
                'workspace_id' => $this->workspaceId,
                'user_id' => $this->userId,
                'title' => $r['title'],
                'shortcut' => $r['shortcut'],
                'content' => $r['content'],
                'category' => $r['category'],
                'channels' => json_encode(['email']),
                'usage_count' => $r['usage_count'],
                'created_at' => $now->copy()->subDays(rand(10, 200)),
                'updated_at' => $now->copy()->subDays(rand(0, 30)),
            ];
        }

        DB::table('canned_responses')->insert($rows);
        $this->command->info('Seeded ' . count($rows) . ' canned responses.');
    }
}
