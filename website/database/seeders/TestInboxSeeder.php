<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\EmailAccount;
use App\Models\Message;
use App\Models\Tag;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TestInboxSeeder extends Seeder
{
    public function run(): void
    {
        $workspace = Workspace::first();

        if (! $workspace) {
            $this->command->error('No workspace found. Please run DemoUserSeeder first.');
            return;
        }

        $user = $workspace->members()->first() ?? User::first();

        $this->command->info("Seeding test inbox for workspace: {$workspace->name}");

        // ── Email Accounts ──────────────────────────────────────────

        $accountDefs = [
            ['email' => 'sarah.johnson@gmail.com',    'display_name' => 'Sarah Johnson',   'provider' => 'gmail'],
            ['email' => 'mike.chen@outlook.com',      'display_name' => 'Mike Chen',        'provider' => 'outlook'],
            ['email' => 'support@acmecorp.com',       'display_name' => 'Acme Support',     'provider' => 'imap'],
            ['email' => 'hello@brightlabs.io',        'display_name' => 'Bright Labs',      'provider' => 'gmail'],
        ];

        $accounts = [];
        foreach ($accountDefs as $i => $def) {
            $accounts[] = EmailAccount::firstOrCreate(
                ['workspace_id' => $workspace->id, 'email' => $def['email']],
                array_merge($def, [
                    'workspace_id' => $workspace->id,
                    'user_id'      => $user?->id,
                    'status'       => 'connected',
                    'is_default'   => $i === 0,
                    'ai_auto_reply' => (bool) rand(0, 1),
                    'last_synced_at' => now()->subMinutes(rand(1, 120)),
                ])
            );
        }

        $this->command->info('Created ' . count($accounts) . ' email accounts.');

        // ── Tags ────────────────────────────────────────────────────
        $tagDefs = [
            ['name' => 'Bug', 'color' => '#EF4444'],
            ['name' => 'Feature Request', 'color' => '#8B5CF6'],
            ['name' => 'Billing', 'color' => '#F59E0B'],
            ['name' => 'Support', 'color' => '#3B82F6'],
            ['name' => 'Urgent', 'color' => '#DC2626'],
            ['name' => 'VIP', 'color' => '#10B981'],
            ['name' => 'Follow Up', 'color' => '#EC4899'],
            ['name' => 'Sales', 'color' => '#06B6D4'],
        ];
        $tags = [];
        foreach ($tagDefs as $td) {
            $tags[] = Tag::firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $td['name']],
                ['color' => $td['color']]
            );
        }
        $this->command->info('Created ' . count($tags) . ' tags.');

        // ── Subjects & body snippets for realism ────────────────────

        $subjects = [
            'Quick question about your pricing plans',
            'Invoice #4821 — payment confirmation',
            'Follow-up: Partnership discussion',
            'Bug report: Login page not loading on mobile',
            'Request for product demo',
            'Urgent: Server downtime alert',
            'New feature suggestion — dark mode',
            'Re: Contract renewal for 2026',
            'Meeting reschedule request',
            'Thank you for your quick response!',
            'Order #9923 — shipping delay',
            'Invitation: Quarterly review meeting',
            'Account verification needed',
            'Feedback on the latest release',
            'Collaboration opportunity',
            'API integration question',
            'Billing discrepancy — need clarification',
            'Welcome aboard! Next steps',
            'Content approval for newsletter',
            'Support ticket #7710 — escalation',
        ];

        $bodies = [
            'Hi there, I wanted to reach out regarding our ongoing conversation. Could you provide an update on the timeline? We\'re eager to move forward with the project.',
            'Thanks for getting back to me so quickly. I\'ve reviewed the proposal and everything looks good. Let\'s schedule a call to finalize the details.',
            'I noticed an issue with the latest build — the login page throws a 500 error on Safari. Could your team take a look? Happy to provide screenshots.',
            'Just a quick heads up — I\'ll be out of office next week. Can we push our meeting to the following Monday?',
            'We\'ve processed your payment of $2,450.00. Please find the receipt attached. Let us know if you have any questions about the invoice.',
            'I\'d love to explore how we can work together. Our team has been using your platform for 6 months and we see great potential for a deeper integration.',
            'The new dashboard looks fantastic! One small suggestion: it would be great to have an export-to-PDF option for the analytics reports.',
            'Following up on my previous email — have you had a chance to review the contract amendments? The deadline is approaching.',
            'Could you help me reset my account password? I\'ve tried the forgot password flow but I\'m not receiving the reset email.',
            'We are experiencing intermittent downtime on the API endpoint. Our monitoring shows 502 errors starting around 3:00 AM UTC.',
        ];

        $contactFirstNames = ['Emma', 'Liam', 'Olivia', 'Noah', 'Ava', 'James', 'Sophia', 'Lucas', 'Mia', 'Ethan',
            'Isabella', 'Mason', 'Charlotte', 'Logan', 'Amelia', 'Aiden', 'Harper', 'Elijah', 'Ella', 'Benjamin'];
        $contactLastNames  = ['Smith', 'Patel', 'Kim', 'Garcia', 'Müller', 'Tanaka', 'Dubois', 'Rossi', 'Silva', 'Wang',
            'Brown', 'Davis', 'Wilson', 'Moore', 'Taylor', 'Anderson', 'Thomas', 'Jackson', 'White', 'Harris'];
        $domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'company.io', 'startup.co', 'enterprise.com', 'mail.com'];
        $companies = ['TechNova Inc.', 'GreenLeaf Solutions', 'Atlas Digital', 'Pinnacle Corp', 'BlueShift Labs',
            'Orion Partners', 'Zenith Systems', 'Apex Ventures', 'Nimbus Cloud', 'Vertex Analytics'];

        // ── Conversations & Messages ────────────────────────────────

        $statuses   = ['open', 'open', 'open', 'open', 'open', 'open', 'closed', 'closed', 'pending', 'pending'];
        $priorities = ['normal', 'normal', 'normal', 'normal', 'normal', 'high', 'high', 'urgent'];
        $channels   = ['email', 'email', 'email', 'email', 'email', 'email', 'email', 'whatsapp', 'sms'];
        $sentiments = ['positive', 'neutral', 'neutral', 'neutral', 'negative'];

        for ($c = 0; $c < 20; $c++) {
            $account      = $accounts[array_rand($accounts)];
            $firstName    = $contactFirstNames[$c];
            $lastName     = $contactLastNames[$c];
            $contactEmail = strtolower($firstName) . '.' . strtolower($lastName) . '@' . $domains[array_rand($domains)];
            $lastMsgAt    = now()->subHours(rand(1, 720)); // up to 30 days ago

            // Create or find contact
            $contact = Contact::firstOrCreate(
                ['workspace_id' => $workspace->id, 'email' => $contactEmail],
                [
                    'first_name'       => $firstName,
                    'last_name'        => $lastName,
                    'company'          => $companies[array_rand($companies)],
                    'status'           => 'active',
                    'lead_score'       => rand(10, 95),
                    'last_contacted_at' => $lastMsgAt,
                ]
            );

            $status   = $statuses[array_rand($statuses)];
            $msgCount = rand(2, 5);

            $conversation = Conversation::create([
                'workspace_id'      => $workspace->id,
                'contact_id'        => $contact->id,
                'email_account_id'  => $account->id,
                'channel'           => $channels[array_rand($channels)],
                'status'            => $status,
                'priority'          => $priorities[array_rand($priorities)],
                'subject'           => $subjects[$c],
                'preview'           => Str::limit($bodies[array_rand($bodies)], 120),
                'sentiment'         => $sentiments[array_rand($sentiments)],
                'is_starred'        => (bool) rand(0, 4) === 0,  // ~20%
                'is_read'           => (bool) rand(0, 2),        // ~67% read
                'is_pinned'         => false,
                'is_ai_handled'     => (bool) rand(0, 3) === 0,  // ~25%
                'messages_count'    => $msgCount,
                'last_message_at'   => $lastMsgAt,
                'resolved_at'       => $status === 'closed' ? $lastMsgAt->copy()->addMinutes(rand(5, 120)) : null,
            ]);

            // Attach 1-3 random tags
            $randomTags = collect($tags)->random(rand(1, 3));
            foreach ($randomTags as $t) {
                \DB::table('conversation_tag')->insertOrIgnore([
                    'conversation_id' => $conversation->id,
                    'tag_id' => $t->id,
                ]);
            }

            // Create messages for this conversation
            $messageTime = $lastMsgAt->copy()->subHours($msgCount); // start before last_message_at

            for ($m = 0; $m < $msgCount; $m++) {
                $isInbound  = $m % 2 === 0; // alternate inbound / outbound
                $messageTime = $messageTime->copy()->addMinutes(rand(5, 180));
                $bodyText   = $bodies[array_rand($bodies)];

                Message::create([
                    'conversation_id'    => $conversation->id,
                    'workspace_id'       => $workspace->id,
                    'direction'          => $isInbound ? 'inbound' : 'outbound',
                    'sender_type'        => $isInbound ? 'contact' : 'agent',
                    'sender_id'          => $isInbound ? null : $user?->id,
                    'type'               => 'message',
                    'subject'            => $m === 0 ? $conversation->subject : null,
                    'body_html'          => '<p>' . e($bodyText) . '</p>',
                    'body_text'          => $bodyText,
                    'from_email'         => $isInbound ? $contactEmail : $account->email,
                    'from_name'          => $isInbound ? "$firstName $lastName" : $account->display_name,
                    'to_emails'          => $isInbound ? [$account->email] : [$contactEmail],
                    'message_id_header'  => '<' . Str::uuid() . '@mail.test>',
                    'delivery_status'    => $isInbound ? 'delivered' : 'sent',
                    'sentiment'          => $sentiments[array_rand($sentiments)],
                    'sent_at'            => $isInbound ? null : $messageTime,
                    'delivered_at'       => $isInbound ? null : $messageTime->copy()->addSeconds(rand(1, 30)),
                ]);
            }
        }

        $this->command->info('Created 20 conversations with contacts and messages.');
        $this->command->info('Done! Run your inbox to see the test data.');
    }
}
