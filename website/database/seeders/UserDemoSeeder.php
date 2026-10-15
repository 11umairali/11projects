<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * UserDemoSeeder — populates a single user's workspace with realistic demo
 * content so a freshly-logged-in demo visitor sees a fully-populated app:
 *
 *   • 200 fake contacts (mixed companies / countries / lead scores)
 *   • 30 conversations, each with 3-7 threaded messages (some with image
 *     attachments, mix of inbound / outbound, mix of channels + tags)
 *   • 10 deals across 1 default pipeline (Lead → Qualified → Proposal →
 *     Negotiation → Won / Lost), assigned to the user
 *   • 10 workflows (3 nodes each: trigger → action → action), all active
 *   • 10 sent campaigns, each with 200 random contacts as recipients
 *
 * Target user: identified by email (defaults to user@mediacity.co.in).
 * Override via env DEMO_USER_EMAIL when calling the seeder.
 *
 * Usage on the server:
 *   php artisan db:seed --class=UserDemoSeeder
 *
 *   # or with a different user
 *   DEMO_USER_EMAIL=other@example.com php artisan db:seed --class=UserDemoSeeder
 *
 * Idempotent-ish: re-running it adds MORE demo data. To reset the demo
 * for that workspace, delete its contacts/conversations/deals/etc first
 * (or call the seeder once on a fresh workspace).
 */
class UserDemoSeeder extends Seeder
{
    /** Target user's email (override via env DEMO_USER_EMAIL). */
    private string $userEmail;

    /** Resolved user / workspace ids — set in run(). */
    private int $userId;
    private int $workspaceId;

    /** Faker-style data pools. */
    private const FIRST_NAMES = [
        'Aarav', 'Priya', 'Rahul', 'Ananya', 'Vikram', 'Neha', 'Arjun', 'Riya',
        'Sarah', 'Michael', 'Emily', 'James', 'Olivia', 'Daniel', 'Sophia', 'Liam',
        'Marco', 'Lucia', 'Andre', 'Yuki', 'Hiroshi', 'Mei', 'Carlos', 'Isabella',
        'Mohammed', 'Fatima', 'Ahmed', 'Aisha', 'Zara', 'Omar', 'Layla', 'Ibrahim',
        'Pierre', 'Camille', 'Antoine', 'Chloé', 'Hans', 'Greta', 'Klaus', 'Eva',
    ];

    private const LAST_NAMES = [
        'Sharma', 'Patel', 'Kumar', 'Singh', 'Mehta', 'Desai', 'Reddy', 'Iyer',
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Davis', 'Miller', 'Wilson',
        'Garcia', 'Martinez', 'Lopez', 'Gonzalez', 'Tanaka', 'Suzuki', 'Yamamoto',
        'Khan', 'Ahmed', 'Rahman', 'Hussain', 'Dubois', 'Martin', 'Bernard',
        'Müller', 'Schmidt', 'Schneider', 'Fischer', 'Rossi', 'Romano', 'Bianchi',
    ];

    private const COMPANIES = [
        'Acme Corp', 'TechHub Inc', 'Bloomly', 'Mintly', 'Sundae Labs', 'Lumos AI',
        'PixelForge', 'Cloud Nimbus', 'Velocity Stack', 'Northwind', 'Globex Co',
        'Initech', 'Hooli', 'Pied Piper', 'Massive Dynamic', 'Stark Industries',
        'Wayne Enterprises', 'Wonka Industries', 'Cyberdyne', 'Tyrell Corp',
    ];

    private const JOB_TITLES = [
        'Marketing Manager', 'Product Owner', 'CEO', 'Head of Growth', 'Sales Lead',
        'Customer Success', 'Engineering Lead', 'Designer', 'Operations Manager',
        'Founder', 'CTO', 'CFO', 'COO', 'Account Executive', 'Solutions Architect',
    ];

    private const COUNTRIES = ['India', 'USA', 'UK', 'Germany', 'France', 'Japan', 'Brazil', 'Canada', 'Australia', 'UAE'];

    private const SUBJECTS = [
        'Quick question about pricing',
        'Demo request — next week?',
        'Issue with last invoice',
        'Loving the new dashboard!',
        'Integration with Stripe failing',
        'Renewal coming up — discount?',
        'Urgent: account locked out',
        'Feature request: dark mode export',
        'Onboarding follow-up',
        'Partnership opportunity',
        'Bug report — campaign send stuck',
        'How does the API rate-limit work?',
        'Thanks for the quick fix',
        'Cancel my subscription',
        'Custom domain setup help',
    ];

    private const INBOUND_BODIES = [
        "Hi team,\n\nJust wanted to reach out about a quick question I had on the pricing page. Is there a way to get a custom quote for an annual plan? We're a small team but we're growing fast.\n\nThanks!\n",
        "Hello,\n\nWe tried setting up the Stripe integration today but the webhook keeps failing with a 401. Is there a setup guide we missed? The dashboard says it's connected but no events come through.\n\nAppreciate any help.\n",
        "Hey,\n\nLove what you've built! Quick feedback — would be amazing if the campaign builder had a dark mode option. Our team works late and the white background is brutal.\n\nKeep up the great work.\n",
        "Hi,\n\nMy account got locked out this morning after a failed login attempt. Can someone reset it? My email is on file. This is pretty urgent — we have a campaign going out today.\n\nThanks,\n",
        "Hello support,\n\nWe're considering MailTrixy for our 50-person team. Could we schedule a demo sometime next week? Tuesday or Wednesday would work best. PST timezone.\n\nLooking forward to hearing back.\n",
    ];

    private const OUTBOUND_BODIES = [
        "Hi there,\n\nThanks for reaching out! Happy to help.\n\nFor annual plans we offer 20% off — I've sent over a custom quote to your email. Let me know if you'd like a quick call to walk through it.\n\nBest regards,\nThe MailTrixy Team\n",
        "Hello,\n\nThanks for the report — I've checked your account and the issue is on our end. The webhook signature check was rejecting valid Stripe events. Just deployed a fix — please try again in 5 minutes.\n\nApologies for the trouble!\n",
        "Hey,\n\nGreat suggestion — dark mode export is on our Q2 roadmap. I'll bump it up the priority list and let you know when it ships.\n\nThanks for being a power user!\n",
        "Hi,\n\nAccount unlocked — you should be able to log in now. I've also enabled 2FA bypass for the next 24 hours so you're not locked out again.\n\nGood luck with the campaign!\n",
        "Hello,\n\nAbsolutely — sent over a calendar link with three time slots Tuesday/Wednesday. Pick whichever works and I'll send a Zoom invite.\n\nLooking forward to the chat!\n",
    ];

    private const TAG_NAMES = [
        ['VIP', '#FF7A6B'], ['Hot Lead', '#FFC94A'], ['Support', '#7B61FF'],
        ['Sales', '#00BFA5'], ['Refund', '#EF4444'], ['Onboarding', '#3B82F6'],
    ];

    public function run(): void
    {
        $this->userEmail = env('DEMO_USER_EMAIL', 'user@mediacity.co.in');

        $user = DB::table('users')->where('email', $this->userEmail)->first();
        if (!$user) {
            $this->command?->error("User {$this->userEmail} not found. Create the account first, then re-run.");
            return;
        }
        $this->userId = (int) $user->id;
        $this->workspaceId = (int) ($user->active_workspace_id ?: $this->resolveWorkspace());
        if (!$this->workspaceId) {
            $this->command?->error("User has no active workspace. Set active_workspace_id first.");
            return;
        }

        $this->command?->info("Seeding demo data for {$this->userEmail} (user#{$this->userId}, workspace#{$this->workspaceId})");

        DB::transaction(function () {
            $emailAccountId = $this->ensureEmailAccount();
            $tagIds         = $this->ensureTags();
            $contactIds     = $this->seedContacts(200);
            $this->command?->info('  ✓ 200 contacts');

            $this->seedConversations(30, $emailAccountId, $contactIds, $tagIds);
            $this->command?->info('  ✓ 30 conversations with messages + attachments');

            [$pipelineId, $stageIds] = $this->ensurePipeline();
            $this->seedDeals(10, $pipelineId, $stageIds, $contactIds);
            $this->command?->info('  ✓ 10 deals');

            $this->seedWorkflows(10);
            $this->command?->info('  ✓ 10 workflows');

            $this->seedCampaigns(10, $emailAccountId, $contactIds);
            $this->command?->info('  ✓ 10 sent campaigns (200 recipients each)');
        });

        $this->command?->info("Done. Log in as {$this->userEmail} to see the demo data.");
    }

    private function resolveWorkspace(): int
    {
        // Prefer the first workspace this user is a member of.
        $row = DB::table('workspace_members')->where('user_id', $this->userId)->first();
        if ($row) {
            DB::table('users')->where('id', $this->userId)->update(['active_workspace_id' => $row->workspace_id]);
            return (int) $row->workspace_id;
        }
        return 0;
    }

    private function ensureEmailAccount(): int
    {
        $existing = DB::table('email_accounts')
            ->where('workspace_id', $this->workspaceId)
            ->where('user_id', $this->userId)
            ->first();
        if ($existing) return (int) $existing->id;

        return DB::table('email_accounts')->insertGetId([
            'uuid'             => (string) Str::uuid(),
            'workspace_id'     => $this->workspaceId,
            'user_id'          => $this->userId,
            'email'            => 'support@demo.mailtrixy.test',
            'display_name'     => 'Demo Inbox',
            'provider'         => 'imap',
            'imap_host'        => 'imap.demo.local',
            'imap_port'        => 993,
            'imap_username'    => 'support@demo.mailtrixy.test',
            'imap_password'    => 'demo',
            'imap_encryption'  => 'ssl',
            'smtp_host'        => 'smtp.demo.local',
            'smtp_port'        => 587,
            'smtp_username'    => 'support@demo.mailtrixy.test',
            'smtp_password'    => 'demo',
            'smtp_encryption'  => 'tls',
            'status'           => 'connected',
            'is_default'       => 1,
            'ai_auto_reply'    => 0,
            'last_synced_at'   => now(),
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    /** @return array<int> tag ids */
    private function ensureTags(): array
    {
        $ids = [];
        foreach (self::TAG_NAMES as $i => [$name, $color]) {
            $row = DB::table('tags')
                ->where('workspace_id', $this->workspaceId)
                ->where('name', $name)
                ->first();
            $ids[] = $row?->id ?: DB::table('tags')->insertGetId([
                'workspace_id' => $this->workspaceId,
                'name'         => $name,
                'color'        => $color,
                'sort_order'   => $i,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        }
        return $ids;
    }

    /** @return array<int> contact ids */
    private function seedContacts(int $count): array
    {
        $ids = [];
        $now = now();
        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $company = self::COMPANIES[array_rand(self::COMPANIES)];
            $email = strtolower("{$first}.{$last}." . random_int(100, 9999) . '@' . str_replace([' ', "'", ',', '.'], '', strtolower($company)) . '.com');
            $ids[] = DB::table('contacts')->insertGetId([
                'uuid'         => (string) Str::uuid(),
                'workspace_id' => $this->workspaceId,
                'first_name'   => $first,
                'last_name'    => $last,
                'email'        => $email,
                'phone'        => '+1' . random_int(2000000000, 9999999999),
                'company'      => $company,
                'job_title'    => self::JOB_TITLES[array_rand(self::JOB_TITLES)],
                'country'      => self::COUNTRIES[array_rand(self::COUNTRIES)],
                'lead_score'   => random_int(0, 100),
                'status'       => 'active',
                'last_contacted_at' => $now->copy()->subDays(random_int(0, 60)),
                'created_at'   => $now->copy()->subDays(random_int(0, 180)),
                'updated_at'   => $now,
            ]);
        }
        return $ids;
    }

    private function seedConversations(int $count, int $emailAccountId, array $contactIds, array $tagIds): void
    {
        $channels = ['email', 'whatsapp', 'sms', 'live_chat', 'telegram'];
        // conversations.status enum: open, pending, closed, snoozed, spam
        $statuses = ['open', 'pending', 'closed', 'open', 'open']; // weighted toward open
        $sentiments = ['positive', 'neutral', 'negative', 'neutral'];

        for ($i = 0; $i < $count; $i++) {
            $contactId = $contactIds[array_rand($contactIds)];
            $contact = DB::table('contacts')->where('id', $contactId)->first();
            $subject = self::SUBJECTS[array_rand(self::SUBJECTS)];
            $channel = $channels[array_rand($channels)];
            $status  = $statuses[array_rand($statuses)];
            $sentiment = $sentiments[array_rand($sentiments)];
            $msgCount = random_int(3, 7);
            $startedAt = now()->subDays(random_int(0, 45))->subHours(random_int(0, 23));

            // Pick 1-2 random tags as JSON tag names (matches Conversation::tags cast).
            $picked = collect($tagIds)->shuffle()->take(random_int(1, 2))->all();
            $tagNames = DB::table('tags')->whereIn('id', $picked)->pluck('name')->all();

            $convId = DB::table('conversations')->insertGetId([
                'uuid'             => (string) Str::uuid(),
                'workspace_id'     => $this->workspaceId,
                'contact_id'       => $contactId,
                'email_account_id' => $channel === 'email' ? $emailAccountId : null,
                'assigned_to'      => $this->userId,
                'channel'          => $channel,
                'status'           => $status,
                'priority'         => ['low','normal','high'][array_rand(['low','normal','high'])],
                'subject'          => $subject,
                'preview'          => Str::limit(self::INBOUND_BODIES[0], 120),
                'sentiment'        => $sentiment,
                'is_starred'       => random_int(0, 4) === 0 ? 1 : 0,
                'is_pinned'        => 0,
                'is_read'          => $status === 'closed' ? 1 : (random_int(0, 1)),
                'is_ai_handled'    => random_int(0, 3) === 0 ? 1 : 0,
                'messages_count'   => $msgCount,
                'ai_replies_count' => random_int(0, 2),
                'tags'             => json_encode($tagNames),
                'last_message_at'  => $startedAt->copy()->addMinutes($msgCount * 30),
                'first_response_at'=> $startedAt->copy()->addMinutes(15),
                'resolved_at'      => $status === 'closed' ? $startedAt->copy()->addDays(2) : null,
                'created_at'       => $startedAt,
                'updated_at'       => $startedAt->copy()->addMinutes($msgCount * 30),
            ]);

            // Threaded messages — alternating inbound/outbound.
            for ($m = 0; $m < $msgCount; $m++) {
                $isInbound = $m % 2 === 0;
                $msgTime = $startedAt->copy()->addMinutes($m * 30);
                $body = $isInbound
                    ? self::INBOUND_BODIES[array_rand(self::INBOUND_BODIES)]
                    : self::OUTBOUND_BODIES[array_rand(self::OUTBOUND_BODIES)];

                $msgId = DB::table('messages')->insertGetId([
                    'uuid'             => (string) Str::uuid(),
                    'conversation_id'  => $convId,
                    'workspace_id'     => $this->workspaceId,
                    'direction'        => $isInbound ? 'inbound' : 'outbound',
                    // sender_type enum: contact, agent, ai, system
                    // sender_id has a hard FK to users.id, so contact-side
                    // messages leave it NULL (sender_type=contact + from_email
                    // is enough to identify the sender for inbound mail).
                    'sender_type'      => $isInbound ? 'contact' : 'agent',
                    'sender_id'        => $isInbound ? null : $this->userId,
                    'type'             => 'message',
                    'body_html'        => '<p>' . nl2br(e($body)) . '</p>',
                    'body_text'        => $body,
                    'subject'          => $m === 0 ? $subject : 'Re: ' . $subject,
                    'from_email'       => $isInbound ? $contact->email : 'support@demo.mailtrixy.test',
                    'from_name'        => $isInbound ? trim(($contact->first_name ?? '') . ' ' . ($contact->last_name ?? '')) : 'Demo Inbox',
                    'to_emails'        => json_encode([$isInbound ? 'support@demo.mailtrixy.test' : $contact->email]),
                    'message_id_header'=> '<' . Str::random(20) . '@demo.mailtrixy.test>',
                    'sentiment'        => $sentiment,
                    'detected_language'=> 'en',
                    'delivery_status'  => 'delivered',
                    'sent_at'          => $msgTime,
                    'delivered_at'     => $msgTime,
                    'opened_at'        => $isInbound ? null : $msgTime->copy()->addMinutes(random_int(5, 120)),
                    'created_at'       => $msgTime,
                    'updated_at'       => $msgTime,
                ]);

                // Attach an inline image to ~30% of messages (uses placehold.co
                // URL style stored as storage_path so demo doesn't need disk files).
                if (random_int(0, 9) < 3) {
                    DB::table('attachments')->insert([
                        'message_id'        => $msgId,
                        'workspace_id'      => $this->workspaceId,
                        'filename'          => 'screenshot-' . random_int(100, 999) . '.png',
                        'original_filename' => 'screenshot.png',
                        'mime_type'         => 'image/png',
                        'size'              => random_int(45_000, 380_000),
                        'storage_path'      => 'https://placehold.co/600x400/7B61FF/FFFFFF?text=Demo+Attachment',
                        'thumbnail_path'    => 'https://placehold.co/200x140/7B61FF/FFFFFF?text=Demo',
                        'is_inline'         => 0,
                        'created_at'        => $msgTime,
                        'updated_at'        => $msgTime,
                    ]);
                }
            }
        }
    }

    /** @return array{0:int,1:array<int>} [pipelineId, stageIds-in-order] */
    private function ensurePipeline(): array
    {
        $pipeline = DB::table('pipelines')->where('workspace_id', $this->workspaceId)->first();
        if (!$pipeline) {
            $pipelineId = DB::table('pipelines')->insertGetId([
                'workspace_id' => $this->workspaceId,
                'name'         => 'Sales Pipeline',
                'is_default'   => 1,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);
        } else {
            $pipelineId = (int) $pipeline->id;
        }

        $stageDefs = [
            ['Lead',        '#94A3B8', 10],
            ['Qualified',   '#3B82F6', 25],
            ['Proposal',    '#F59E0B', 50],
            ['Negotiation', '#7B61FF', 75],
            ['Won',         '#10B981', 100],
            ['Lost',        '#EF4444', 0],
        ];
        $stageIds = [];
        foreach ($stageDefs as $i => [$name, $color, $prob]) {
            $existing = DB::table('deal_stages')
                ->where('pipeline_id', $pipelineId)
                ->where('name', $name)
                ->first();
            $stageIds[] = $existing?->id ?: DB::table('deal_stages')->insertGetId([
                'pipeline_id'      => $pipelineId,
                'name'             => $name,
                'color'            => $color,
                'win_probability'  => $prob,
                'sort_order'       => $i,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
        }
        return [$pipelineId, $stageIds];
    }

    private function seedDeals(int $count, int $pipelineId, array $stageIds, array $contactIds): void
    {
        $titles = [
            'Annual Pro Plan upgrade', 'Enterprise migration', 'White-label add-on',
            'Custom integration sprint', 'Premium SMS bundle', 'AI credits top-up',
            'Multi-workspace expansion', 'Dedicated support contract',
            'Onboarding & training package', 'Custom CNAME setup',
        ];
        $statuses = ['open', 'open', 'open', 'open', 'open', 'open', 'open', 'won', 'won', 'lost'];

        $wonStageId  = $stageIds[count($stageIds) - 2] ?? $stageIds[0]; // 'Won' (second-to-last)
        $lostStageId = $stageIds[count($stageIds) - 1] ?? $stageIds[0]; // 'Lost' (last)

        for ($i = 0; $i < $count; $i++) {
            $stageIdx = array_rand($stageIds);
            $status   = $statuses[$i] ?? 'open';
            $stageId  = match ($status) {
                'won'   => $wonStageId,
                'lost'  => $lostStageId,
                default => $stageIds[$stageIdx],
            };
            $createdAt = now()->subDays(random_int(0, 90));

            DB::table('deals')->insert([
                'uuid'                => (string) Str::uuid(),
                'workspace_id'        => $this->workspaceId,
                'contact_id'          => $contactIds[array_rand($contactIds)],
                'pipeline_id'         => $pipelineId,
                'deal_stage_id'       => (int) $stageId,
                'assigned_to'         => $this->userId,
                'title'               => $titles[$i] ?? "Demo Deal #{$i}",
                'value'               => random_int(500, 25000),
                'currency'            => 'USD',
                'expected_close_date' => $createdAt->copy()->addDays(random_int(7, 60))->toDateString(),
                'status'              => $status,
                'won_at'              => $status === 'won' ? $createdAt->copy()->addDays(random_int(5, 40)) : null,
                'lost_at'             => $status === 'lost' ? $createdAt->copy()->addDays(random_int(5, 40)) : null,
                'lost_reason'         => $status === 'lost' ? 'Budget cut' : null,
                'notes'               => 'Auto-generated demo deal.',
                'created_at'          => $createdAt,
                'updated_at'          => $createdAt,
            ]);
        }
    }

    private function seedWorkflows(int $count): void
    {
        $workflowDefs = [
            ['Welcome Email Drip',          'New contact → wait 1h → send welcome email → wait 1d → send tips email'],
            ['VIP Auto-Tag',                'New conversation → if subject contains "urgent" → add tag VIP'],
            ['Hot Lead Notify',             'Form submitted → assign to user → send Slack notification'],
            ['Refund Request Escalation',   'Keyword "refund" → reply template → assign to senior agent'],
            ['Demo Request → Calendar',     'Form: demo request → send Calendly link → notify sales lead'],
            ['Cart Abandonment',            'Trigger: cart abandoned → wait 1h → send reminder → wait 1d → send 10% off'],
            ['NPS Follow-up',               'NPS score < 7 → assign to CSM → send check-in email'],
            ['Cold Lead Re-engagement',     '90 days no activity → send re-engagement email'],
            ['New Customer Onboarding',     'Deal won → send onboarding kit → schedule kickoff'],
            ['Out-of-Office Auto-Reply',    'Outside business hours → send AOR template → tag conversation'],
        ];

        for ($i = 0; $i < $count; $i++) {
            [$name, $desc] = $workflowDefs[$i] ?? ["Demo Workflow {$i}", 'Auto-generated demo workflow'];
            $workflowId = DB::table('workflows')->insertGetId([
                'uuid'          => (string) Str::uuid(),
                'workspace_id'  => $this->workspaceId,
                'created_by'    => $this->userId,
                'name'          => $name,
                'description'   => $desc,
                'status'        => 'active',
                'canvas_data'   => json_encode(['nodes' => [], 'edges' => []]),
                'version'       => 1,
                'executions_count' => random_int(5, 250),
                'webhook_token' => Str::random(40),
                'created_at'    => now()->subDays(random_int(0, 60)),
                'updated_at'    => now(),
            ]);

            // 3 nodes: trigger → action → action
            DB::table('workflow_nodes')->insert([
                ['workflow_id' => $workflowId, 'type' => 'trigger', 'subtype' => 'new_conversation', 'config' => json_encode([]), 'position_x' => 100, 'position_y' => 100, 'created_at' => now(), 'updated_at' => now()],
                ['workflow_id' => $workflowId, 'type' => 'action',  'subtype' => 'send_email',       'config' => json_encode(['template' => 'welcome']), 'position_x' => 300, 'position_y' => 100, 'created_at' => now(), 'updated_at' => now()],
                ['workflow_id' => $workflowId, 'type' => 'action',  'subtype' => 'add_tag',          'config' => json_encode(['tag' => 'demo']), 'position_x' => 500, 'position_y' => 100, 'created_at' => now(), 'updated_at' => now()],
            ]);
        }
    }

    private function seedCampaigns(int $count, int $emailAccountId, array $contactIds): void
    {
        $names = [
            'October Newsletter', 'Black Friday Preview', 'New Feature Announcement',
            'Customer Spotlight', 'Year-End Recap', 'Welcome Back Campaign',
            'Product Tips Series #1', 'Holiday Greetings', 'Annual Survey',
            'Referral Program Launch',
        ];
        $subjects = [
            'Big news from MailTrixy this month', 'Our biggest sale of the year is coming',
            'Meet our newest feature: AI Auto-Escalation', 'How TechHub grew 3× with MailTrixy',
            'Your year-in-review is ready', "We've missed you — here's 20% off",
            '5 tips to triple your reply rate', 'Wishing you a wonderful holiday',
            'Help shape MailTrixy 2.0', 'Earn $50 for every friend you refer',
        ];

        for ($i = 0; $i < $count; $i++) {
            $sentAt = now()->subDays(random_int(1, 30));
            $sentCount      = 200;
            $deliveredCount = random_int(185, 199);
            $openedCount    = random_int(60, 140);
            $clickedCount   = random_int(15, $openedCount);
            $bouncedCount   = $sentCount - $deliveredCount;
            $unsubsCount    = random_int(0, 5);

            $bodyHtml = '<h1>' . ($subjects[$i] ?? "Campaign #{$i}") . '</h1>'
                . '<p>Hi {{first_name}},</p>'
                . '<p>This is a demo campaign body. <strong>Replace with your real copy.</strong></p>'
                . '<p><img src="https://placehold.co/600x200/7B61FF/FFFFFF?text=MailTrixy" alt="banner"></p>'
                . '<p>Best,<br>The MailTrixy Team</p>';

            $campaignId = DB::table('campaigns')->insertGetId([
                'uuid'              => (string) Str::uuid(),
                'workspace_id'      => $this->workspaceId,
                'created_by'        => $this->userId,
                'email_account_id'  => $emailAccountId,
                'name'              => $names[$i] ?? "Demo Campaign #{$i}",
                'type'              => 'regular',
                'channel'           => 'email',
                'status'            => 'sent',
                'subject'           => $subjects[$i] ?? "Demo subject #{$i}",
                'body_html'         => $bodyHtml,
                'body_text'         => strip_tags($bodyHtml),
                'preview_text'      => 'Big news inside →',
                'audience_type'     => 'all',
                'recipients_count'  => $sentCount,
                'emails_per_minute' => 60,
                'sent_count'        => $sentCount,
                'delivered_count'   => $deliveredCount,
                'opened_count'      => $openedCount,
                'clicked_count'     => $clickedCount,
                'bounced_count'     => $bouncedCount,
                'unsubscribed_count'=> $unsubsCount,
                'sent_at'           => $sentAt,
                'completed_at'      => $sentAt->copy()->addMinutes(15),
                'created_at'        => $sentAt->copy()->subHours(2),
                'updated_at'        => $sentAt->copy()->addMinutes(15),
            ]);

            // 200 random recipients per campaign — sample with replacement is
            // fine for demo data (occasional duplicates won't break the UI).
            $picked = collect($contactIds)->shuffle()->take(200)->values();
            $rows = [];
            foreach ($picked as $idx => $cid) {
                $contact = DB::table('contacts')->where('id', $cid)->first();
                if (!$contact) continue;
                $opened = $idx < $openedCount;
                $clicked = $idx < $clickedCount;
                $bounced = $idx >= $deliveredCount;
                $rows[] = [
                    'uuid'           => (string) Str::uuid(),
                    'campaign_id'    => $campaignId,
                    'contact_id'     => $cid,
                    'email'          => $contact->email,
                    'status'         => $bounced ? 'bounced' : ($clicked ? 'clicked' : ($opened ? 'opened' : 'delivered')),
                    'sent_at'        => $sentAt,
                    'opened_at'      => $opened ? $sentAt->copy()->addMinutes(random_int(2, 240)) : null,
                    'clicked_at'     => $clicked ? $sentAt->copy()->addMinutes(random_int(5, 360)) : null,
                    'bounced_at'     => $bounced ? $sentAt->copy()->addMinutes(random_int(1, 5)) : null,
                    'created_at'     => $sentAt,
                    'updated_at'     => $sentAt,
                ];
            }
            // Insert in chunks of 50 to keep packet size sensible
            foreach (array_chunk($rows, 50) as $chunk) {
                DB::table('campaign_recipients')->insert($chunk);
            }
        }
    }
}
