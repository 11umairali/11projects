<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class DemoConversationSeeder extends Seeder
{
    public function run(): void
    {
        $wsId = 1;
        $now = Carbon::now();

        // Create a dummy email account
        $emailAccountId = DB::table('email_accounts')->insertGetId([
            'workspace_id' => $wsId,
            'user_id' => 1,
            'email' => 'demo@mailtrixy.com',
            'provider' => 'imap',
            'status' => 'connected',
            'imap_host' => 'imap.gmail.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'demo@mailtrixy.com',
            'uuid' => Str::uuid(),
            'display_name' => 'Demo Account',
            'imap_password' => 'dummy',
            'last_synced_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $names = [
            ['Sarah Johnson', 'sarah.johnson@techcorp.com'],
            ['Michael Chen', 'michael.chen@startup.io'],
            ['Emily Rodriguez', 'emily.r@designhub.co'],
            ['James Wilson', 'james.w@enterprise.com'],
            ['Priya Patel', 'priya.patel@cloudnine.dev'],
            ['David Kim', 'david.kim@finserv.com'],
            ['Lisa Thompson', 'lisa.t@agency.co'],
            ['Alex Martinez', 'alex.m@retailplus.com'],
            ['Rachel Green', 'rachel.g@mediahouse.io'],
            ['Tom Anderson', 'tom.a@logistics.com'],
            ['Nina Shah', 'nina.shah@healthtech.co'],
            ['Chris Baker', 'chris.b@edtech.com'],
            ['Amanda Lee', 'amanda.lee@saasify.io'],
            ['Robert Taylor', 'robert.t@consulting.com'],
            ['Sophie Williams', 'sophie.w@marketpro.co'],
            ['Daniel Brown', 'daniel.b@devops.io'],
            ['Jessica Davis', 'jessica.d@hrtech.com'],
            ['Mark Johnson', 'mark.j@analytics.co'],
            ['Olivia White', 'olivia.w@ecommerce.com'],
            ['Kevin Harris', 'kevin.h@security.io'],
        ];

        $subjects = [
            'Re: Q2 Marketing Budget Approval',
            'Meeting reschedule - Product Demo',
            'Invoice #4521 - Payment Confirmation',
            'New Feature Request: Dashboard Analytics',
            'Partnership Proposal - Strategic Alliance',
            'Bug Report: Login Page Issue',
            'Weekly Team Update - Sprint 14',
            'Contract Renewal Discussion',
            'Customer Feedback Summary - March',
            'Re: API Integration Documentation',
            'Onboarding Schedule for New Hires',
            'Security Audit Results - Action Required',
            'Product Launch Timeline Update',
            'Re: Support Ticket #8834',
            'Quarterly Revenue Report',
            'Design Review: Mobile App Redesign',
            'Server Migration Plan',
            'Re: Pricing Discussion',
            'Event Planning - Annual Conference',
            'Performance Review Templates',
            'Re: Database Optimization',
            'New Client Onboarding - Acme Corp',
            'Marketing Campaign Results',
            'Re: Deployment Pipeline Fix',
            'Board Meeting Agenda - April',
            'Compliance Update - GDPR Changes',
            'Re: Customer Churn Analysis',
            'Training Workshop Registration',
            'Infrastructure Cost Reduction',
            'Re: Feature Flag Implementation',
            'Sales Pipeline Review',
            'Content Calendar - Q2',
            'Re: Microservices Architecture',
            'Vendor Evaluation Report',
            'Re: A/B Testing Results',
            'Team Building Event Ideas',
            'Release Notes v2.5.0',
            'Re: Data Migration Strategy',
            'Customer Success Metrics',
            'Re: CI/CD Pipeline Improvements',
            'Brand Guidelines Update',
            'Re: Load Balancer Configuration',
            'Hiring Plan - Engineering Team',
            'Product Roadmap Discussion',
            'Re: SSL Certificate Renewal',
            'Monthly Analytics Dashboard',
            'Re: Webhook Integration',
            'Social Media Strategy',
            'Re: Cache Invalidation Issue',
            'Investor Update - Q1 2026',
        ];

        $previews = [
            'Hi team, I have reviewed the budget proposal and would like to discuss a few adjustments before we finalize everything for next quarter.',
            'Thanks for the quick response. Let me know if Tuesday at 3 PM works better for the demo session with the client.',
            'Payment has been processed successfully. Please find the receipt attached for your records and accounting.',
            'We have been getting requests from multiple clients for real-time analytics on the dashboard. Can we prioritize this?',
            'I would love to explore a potential partnership between our organizations. Let me outline the key benefits for both sides.',
            'Users are reporting a 500 error when trying to log in with SSO. This seems to be affecting Chrome users only on Windows.',
            'Here is the summary of what we accomplished this sprint. We closed 23 tickets and shipped 4 new features to production.',
            'Our current contract expires on May 15th. I would like to discuss renewal terms and any adjustments needed.',
            'Based on our latest NPS survey, customer satisfaction has improved by 12% compared to last quarter. Great progress!',
            'The API docs have been updated with the new endpoints. Please review the authentication section and let me know.',
        ];

        $priorities = ['normal', 'normal', 'normal', 'high', 'urgent', 'normal', 'normal', 'high', 'normal', 'normal'];
        $statuses = ['open', 'open', 'open', 'open', 'open', 'closed', 'open', 'open', 'snoozed', 'open'];

        for ($i = 0; $i < 50; $i++) {
            $nameData = $names[$i % count($names)];
            $contactEmail = $i < 20 ? $nameData[1] : 'contact' . $i . '@example.com';

            $nameParts = explode(' ', $nameData[0], 2);
            $contactId = DB::table('contacts')->insertGetId([
                'workspace_id' => $wsId,
                'uuid' => Str::uuid(),
                'first_name' => $nameParts[0],
                'last_name' => $nameParts[1] ?? '',
                'email' => $contactEmail,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $time = $now->copy()->subMinutes(rand(5, 2880));
            $convId = DB::table('conversations')->insertGetId([
                'workspace_id' => $wsId,
                'contact_id' => $contactId,
                'email_account_id' => $emailAccountId,
                'channel' => 'email',
                'status' => $statuses[$i % count($statuses)],
                'priority' => $priorities[$i % count($priorities)],
                'subject' => $subjects[$i % count($subjects)],
                'preview' => $previews[$i % count($previews)],
                'is_read' => $i > 10,
                'is_starred' => $i % 7 === 0,
                'is_pinned' => false,
                'messages_count' => rand(1, 8),
                'last_message_at' => $time,
                'uuid' => Str::uuid(),
                'created_at' => $time,
                'updated_at' => $time,
            ]);

            // Add 1-3 messages per conversation
            $msgCount = rand(1, 3);
            for ($m = 1; $m <= $msgCount; $m++) {
                $msgTime = $time->copy()->addMinutes($m * rand(5, 60));
                $dir = $m === 1 ? 'inbound' : ($m % 2 === 0 ? 'outbound' : 'inbound');
                $body = $previews[$i % count($previews)];

                DB::table('messages')->insert([
                    'conversation_id' => $convId,
                    'workspace_id' => $wsId,
                    'uuid' => Str::uuid(),
                    'direction' => $dir,
                    'sender_type' => $dir === 'inbound' ? 'contact' : 'agent',
                    'sender_id' => $dir === 'outbound' ? 1 : null,
                    'type' => 'message',
                    'body_html' => '<div style="font-family:system-ui,sans-serif;padding:20px;line-height:1.7;color:#333"><p style="margin:0 0 16px">' . $body . '</p><p style="margin:0;color:#666">Best regards,<br><strong>' . $nameData[0] . '</strong></p></div>',
                    'body_text' => $body,
                    'subject' => $subjects[$i % count($subjects)],
                    'from_email' => $dir === 'inbound' ? $contactEmail : 'demo@mailtrixy.com',
                    'from_name' => $dir === 'inbound' ? $nameData[0] : 'You',
                    'to_emails' => json_encode([$dir === 'inbound' ? 'demo@mailtrixy.com' : $contactEmail]),
                    'message_id_header' => '<' . Str::uuid() . '@mailtrixy.com>',
                    'delivery_status' => 'delivered',
                    'sent_at' => $msgTime,
                    'delivered_at' => $msgTime,
                    'created_at' => $msgTime,
                    'updated_at' => $msgTime,
                ]);
            }
        }

        $this->command->info('Seeded 50 demo conversations with messages.');
    }
}
