<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * 100+ clean, minimal, enterprise-grade email templates.
 * Design: Flat colors, white space, clean typography — Stripe/Linear aesthetic.
 * No gradients. No emojis. No flashy effects. Just premium.
 */
class EmailTemplateEnterprise extends Seeder
{
    private const H = '#1A1A2E';   // Heading
    private const B = '#4A5568';   // Body text
    private const M = '#9CA3AF';   // Muted
    private const W = '#FFFFFF';   // White
    private const BG = '#F9FAFB';  // Light bg
    private const BD = '#E5E7EB';  // Border

    public function run(): void
    {
        foreach ($this->all() as $t) {
            EmailTemplate::updateOrCreate(
                ['name' => $t['name']],
                ['workspace_id' => null, 'category' => $t['category'], 'blocks' => $t['blocks'], 'is_default' => true]
            );
        }
    }

    private function hdr(string $color = '#4F46E5'): array
    {
        return ['type' => 'header', 'data' => ['logo_url' => '', 'company_name' => '{company}', 'bg_color' => $color]];
    }

    private function sp(string $h = '12'): array
    {
        return ['type' => 'spacer', 'data' => ['height' => $h]];
    }

    private function txt(string $html, string $align = 'left', string $size = '15'): array
    {
        return ['type' => 'text', 'data' => ['content' => $html, 'align' => $align, 'font_size' => $size]];
    }

    private function btn(string $text, string $color = '#4F46E5', string $url = '#'): array
    {
        return ['type' => 'button', 'data' => ['text' => $text, 'url' => $url, 'bg_color' => $color, 'text_color' => self::W, 'align' => 'center']];
    }

    private function div(): array
    {
        return ['type' => 'divider', 'data' => ['color' => self::BD, 'width' => '100', 'style' => 'solid']];
    }

    private function cols(string $left, string $right): array
    {
        return ['type' => 'columns', 'data' => ['left_content' => $left, 'right_content' => $right]];
    }

    private function ftr(): array
    {
        return ['type' => 'footer', 'data' => ['text' => '{company} | 123 Business Ave, Suite 100', 'unsubscribe_text' => 'Unsubscribe from these emails']];
    }

    private function heading(string $text): string
    {
        return '<h2 style="margin:0 0 10px;font-size:22px;font-weight:700;color:' . self::H . ';">' . $text . '</h2>';
    }

    private function para(string $text): string
    {
        return '<p style="margin:0 0 14px;font-size:15px;line-height:1.65;color:' . self::B . ';">' . $text . '</p>';
    }

    private function card(string $title, string $body, string $accent = '#4F46E5'): string
    {
        return '<div style="background:' . self::BG . ';border-left:3px solid ' . $accent . ';border-radius:6px;padding:16px;">'
            . '<p style="margin:0 0 4px;font-weight:600;font-size:14px;color:' . self::H . ';">' . $title . '</p>'
            . '<p style="margin:0;font-size:13px;color:' . self::B . ';">' . $body . '</p></div>';
    }

    private function metric(string $value, string $label, string $color = '#4F46E5'): string
    {
        return '<div style="text-align:center;padding:16px;"><p style="margin:0;font-size:28px;font-weight:700;color:' . $color . ';">' . $value . '</p>'
            . '<p style="margin:4px 0 0;font-size:12px;color:' . self::M . ';text-transform:uppercase;letter-spacing:0.5px;">' . $label . '</p></div>';
    }

    private function listItem(string $text): string
    {
        return '<div style="padding:6px 0;display:flex;align-items:flex-start;gap:8px;"><span style="color:#10B981;font-size:14px;line-height:1.6;">&#10003;</span><span style="font-size:14px;color:' . self::B . ';line-height:1.6;">' . $text . '</span></div>';
    }

    private function all(): array
    {
        return array_merge(
            $this->saasTemplates(),
            $this->ecommerceTemplates(),
            $this->agencyTemplates(),
            $this->realEstateTemplates(),
            $this->healthcareTemplates(),
            $this->financeTemplates(),
            $this->educationTemplates(),
            $this->nonprofitTemplates(),
            $this->hospitalityTemplates(),
            $this->fitnessTemplates(),
            $this->recruitmentTemplates(),
            $this->legalTemplates(),
            $this->transactionalTemplates(),
            $this->engagementTemplates(),
            $this->internalTemplates(),
        );
    }

    // =====================================================================
    //  SAAS (15 templates)
    // =====================================================================
    private function saasTemplates(): array
    {
        $c = '#4F46E5';
        return [
            ['name' => 'SaaS — Welcome', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Welcome to {company}, {first_name}') . $this->para('Your account is ready. Here are three things to do first to get the most out of your new account.')), $this->txt($this->card('1. Complete your profile', 'Add your name, photo, and timezone so your team recognises you.', $c) . '<div style="height:10px;"></div>' . $this->card('2. Connect your tools', 'Integrate with the apps you already use in two clicks.', $c) . '<div style="height:10px;"></div>' . $this->card('3. Invite your team', 'Collaboration is better together. Invite colleagues to join.', $c)), $this->btn('Go to Dashboard', $c), $this->ftr()]],
            ['name' => 'SaaS — Trial Ending (3 Days)', 'category' => 'saas', 'blocks' => [$this->hdr('#D97706'), $this->sp(), $this->txt($this->heading('Your trial ends in 3 days') . $this->para('Hi {first_name}, you have been making great progress. Upgrade now to keep your data and unlock all features.')), $this->txt('<div style="background:#FFFBEB;border:1px solid #FDE68A;border-radius:8px;padding:16px;">' . $this->listItem('All contacts and conversations preserved') . $this->listItem('AI replies and automations stay active') . $this->listItem('No interruption to your workflows') . '</div>'), $this->btn('Upgrade Now', '#D97706'), $this->txt('<p style="text-align:center;font-size:13px;color:' . self::M . ';">Plans start at $9/mo. Cancel anytime.</p>', 'center'), $this->ftr()]],
            ['name' => 'SaaS — Trial Expired', 'category' => 'saas', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('Your trial has ended') . $this->para('{first_name}, your trial period is over. Your account is now on the free plan with limited features. Upgrade to restore full access.')), $this->btn('Restore My Account', '#DC2626'), $this->ftr()]],
            ['name' => 'SaaS — Feature Announcement', 'category' => 'product', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt('<p style="margin:0 0 6px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">What\'s New</p>' . $this->heading('Introducing Workflow Automation') . $this->para('Build powerful automations without code. Set triggers, add conditions, and let the system handle the rest.')), $this->cols($this->card('Visual Builder', 'Drag and drop nodes to create any workflow.', $c), $this->card('Smart Conditions', 'Branch logic based on contact data, engagement, or custom fields.', '#10B981')), $this->sp('8'), $this->cols($this->card('Execution Logs', 'Full audit trail for every workflow run.', '#F59E0B'), $this->card('Templates', '8 pre-built flows to get started in seconds.', '#8B5CF6')), $this->sp(), $this->btn('Try Workflows Now', $c), $this->ftr()]],
            ['name' => 'SaaS — Monthly Usage Report', 'category' => 'reporting', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">Monthly Report</p>' . $this->heading('Your March Performance')), $this->cols($this->metric('12,847', 'Emails Sent', '#10B981'), $this->metric('34.2%', 'Open Rate', '#3B82F6')), $this->cols($this->metric('8.7%', 'Click Rate', '#8B5CF6'), $this->metric('$48.3K', 'Revenue', '#F59E0B')), $this->sp(), $this->btn('View Full Report', '#0F172A'), $this->ftr()]],
            ['name' => 'SaaS — Subscription Confirmed', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Subscription Confirmed') . $this->para('Hi {first_name}, your subscription is now active. Here are your details.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;color:' . self::B . ';"><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid ' . self::BD . ';"><span>Plan</span><strong style="color:' . self::H . ';">Pro</strong></div><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid ' . self::BD . ';"><span>Billing</span><strong style="color:' . self::H . ';">Monthly</strong></div><div style="display:flex;justify-content:space-between;padding:6px 0;"><span>Next Payment</span><strong style="color:' . self::H . ';">April 1, 2026</strong></div></div>'), $this->btn('Manage Billing', $c), $this->ftr()]],
            ['name' => 'SaaS — Payment Failed', 'category' => 'transactional', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('Payment Failed') . $this->para('We were unable to process your payment. Please update your payment method to avoid service interruption.')), $this->btn('Update Payment Method', '#DC2626'), $this->txt('<p style="font-size:13px;color:' . self::M . ';">If you believe this is an error, please contact support.</p>'), $this->ftr()]],
            ['name' => 'SaaS — Account Suspended', 'category' => 'transactional', 'blocks' => [$this->hdr('#991B1B'), $this->sp(), $this->txt($this->heading('Your Account Has Been Restricted') . $this->para('{first_name}, due to a prolonged payment issue, your account features have been restricted. Update your billing to restore access.')), $this->btn('Restore Access', '#991B1B'), $this->ftr()]],
            ['name' => 'SaaS — Team Invitation', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('{first_name} invited you to join {company}') . $this->para('You have been invited to collaborate on the {company} workspace. Accept the invitation to get started.')), $this->btn('Accept Invitation', $c), $this->txt('<p style="font-size:13px;color:' . self::M . ';">This invitation expires in 7 days.</p>', 'center'), $this->ftr()]],
            ['name' => 'SaaS — Password Reset', 'category' => 'transactional', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Reset Your Password') . $this->para('We received a request to reset the password for your account. Click the button below to set a new password.')), $this->btn('Reset Password', '#374151'), $this->txt('<p style="font-size:13px;color:' . self::M . ';">If you did not request this, please ignore this email. This link expires in 60 minutes.</p>'), $this->ftr()]],
            ['name' => 'SaaS — Changelog / Release Notes', 'category' => 'product', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">Release Notes</p>' . $this->heading('Version 2.4 Is Live') . $this->para('Here is what we shipped this week.')), $this->txt($this->card('Multi-channel inbox', 'WhatsApp, SMS, and Telegram messages now appear alongside email.') . '<div style="height:8px;"></div>' . $this->card('AI confidence scores', 'See how confident the AI is before auto-sending replies.', '#10B981') . '<div style="height:8px;"></div>' . $this->card('Bug fixes', '14 bugs squashed including the campaign scheduling issue.', '#6B7280')), $this->btn('See All Changes', $c), $this->ftr()]],
            ['name' => 'SaaS — NPS Survey', 'category' => 'engagement', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt($this->heading('How likely are you to recommend {company}?') . $this->para('Hi {first_name}, we would love your honest feedback. It takes less than 30 seconds.')), $this->txt('<div style="text-align:center;padding:12px;background:' . self::BG . ';border-radius:8px;"><p style="margin:0 0 8px;font-size:13px;color:' . self::M . ';">0 = Not at all &nbsp;&nbsp;&nbsp; 10 = Absolutely</p><p style="margin:0;font-size:20px;letter-spacing:6px;color:' . self::H . ';">0 1 2 3 4 5 6 7 8 9 10</p></div>', 'center'), $this->btn('Share Feedback', '#0F172A'), $this->ftr()]],
            ['name' => 'SaaS — Referral Program', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Give $20, Get $20') . $this->para('Know someone who could use {company}? Share your referral link and you both get $20 credit.')), $this->txt('<div style="background:' . self::BG . ';border:1px dashed ' . self::BD . ';border-radius:8px;padding:14px;text-align:center;"><p style="margin:0;font-size:13px;color:' . self::M . ';">Your referral link</p><p style="margin:4px 0 0;font-size:15px;font-weight:600;color:' . $c . ';word-break:break-all;">https://mailtrixy.com/ref/{first_name}</p></div>', 'center'), $this->btn('Share Now', $c), $this->ftr()]],
            ['name' => 'SaaS — Downgrade Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr('#6B7280'), $this->sp(), $this->txt($this->heading('Plan Downgraded') . $this->para('{first_name}, your plan has been downgraded. You will lose access to the following features at the end of your current billing period.') . '<div style="height:6px;"></div>' . $this->listItem('Workflow automations') . $this->listItem('AI-powered replies') . $this->listItem('Priority support')), $this->btn('Upgrade Back', $c), $this->ftr()]],
            ['name' => 'SaaS — Data Export Ready', 'category' => 'transactional', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Your Data Export Is Ready') . $this->para('The data export you requested is now available for download. The link below will expire in 24 hours.')), $this->btn('Download Export', '#374151'), $this->txt('<p style="font-size:13px;color:' . self::M . ';">File size: 12.4 MB. Format: JSON.</p>', 'center'), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  E-COMMERCE (12 templates)
    // =====================================================================
    private function ecommerceTemplates(): array
    {
        $c = '#059669';
        return [
            ['name' => 'E-Commerce — Order Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Order Confirmed') . $this->para('Hi {first_name}, thank you for your order. We are preparing it now.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid ' . self::BD . ';"><span style="color:' . self::M . ';">Order #</span><strong style="color:' . self::H . ';">ORD-2026-4821</strong></div><div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid ' . self::BD . ';"><span style="color:' . self::M . ';">Items</span><strong style="color:' . self::H . ';">3 items</strong></div><div style="display:flex;justify-content:space-between;padding:6px 0;"><span style="color:' . self::M . ';">Total</span><strong style="color:' . $c . ';">$149.00</strong></div></div>'), $this->btn('Track Your Order', $c), $this->ftr()]],
            ['name' => 'E-Commerce — Shipping Notification', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Order Has Shipped') . $this->para('Great news! Your order is on its way. Estimated delivery: 3-5 business days.')), $this->txt('<div style="background:' . self::BG . ';border-radius:8px;padding:14px;text-align:center;"><p style="margin:0;font-size:12px;color:' . self::M . ';text-transform:uppercase;">Tracking Number</p><p style="margin:4px 0 0;font-size:16px;font-weight:600;color:' . self::H . ';font-family:monospace;">1Z999AA10123456784</p></div>', 'center'), $this->btn('Track Package', $c), $this->ftr()]],
            ['name' => 'E-Commerce — Abandoned Cart', 'category' => 'marketing', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt($this->heading('You left something behind') . $this->para('{first_name}, you have items waiting in your cart. Complete your purchase before they sell out.')), $this->btn('Complete Purchase', '#1F2937'), $this->txt('<p style="font-size:13px;color:' . self::M . ';text-align:center;">Free shipping on orders over $50.</p>', 'center'), $this->ftr()]],
            ['name' => 'E-Commerce — Review Request', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('How was your purchase?') . $this->para('Hi {first_name}, we hope you are enjoying your order. Your feedback helps other customers and helps us improve.')), $this->btn('Leave a Review', $c), $this->ftr()]],
            ['name' => 'E-Commerce — Back in Stock', 'category' => 'marketing', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt($this->heading('Back in Stock') . $this->para('The item you were looking at is back. Stock is limited, so act fast.')), $this->btn('Shop Now', '#1F2937'), $this->ftr()]],
            ['name' => 'E-Commerce — Loyalty Points', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Rewards Update')), $this->cols($this->metric('2,450', 'Points Balance', $c), $this->metric('$24.50', 'Value', '#1F2937')), $this->btn('Redeem Points', $c), $this->ftr()]],
            ['name' => 'E-Commerce — Flash Sale', 'category' => 'marketing', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('24-Hour Flash Sale') . '<p style="font-size:36px;font-weight:800;color:#DC2626;margin:0;">30% OFF</p><p style="font-size:14px;color:' . self::M . ';margin:8px 0 0;">Everything. No exclusions. Ends midnight.</p></div>'), $this->btn('Shop the Sale', '#DC2626'), $this->ftr()]],
            ['name' => 'E-Commerce — Refund Processed', 'category' => 'transactional', 'blocks' => [$this->hdr('#6B7280'), $this->sp(), $this->txt($this->heading('Refund Processed') . $this->para('Your refund of $49.00 has been processed. Please allow 5-10 business days for the amount to appear on your statement.')), $this->ftr()]],
            ['name' => 'E-Commerce — Wishlist Reminder', 'category' => 'engagement', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt($this->heading('Items on Your Wishlist') . $this->para('You have 4 items saved. Some are on sale this week.')), $this->btn('View Wishlist', '#1F2937'), $this->ftr()]],
            ['name' => 'E-Commerce — Seasonal Sale', 'category' => 'marketing', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('Summer Collection Is Here') . $this->para('Fresh arrivals for the season. Free shipping on your first order.') . '</div>'), $this->btn('Shop Summer', '#1F2937'), $this->ftr()]],
            ['name' => 'E-Commerce — VIP Early Access', 'category' => 'marketing', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt($this->heading('VIP Early Access') . $this->para('{first_name}, as a valued customer, you get first access to our new collection before anyone else.')), $this->btn('Shop Early', '#1F2937'), $this->ftr()]],
            ['name' => 'E-Commerce — Re-Order Reminder', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Time to Restock?') . $this->para('It has been about 30 days since your last order. Need a refill?')), $this->btn('Reorder Now', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  AGENCY / PROFESSIONAL SERVICES (10 templates)
    // =====================================================================
    private function agencyTemplates(): array
    {
        $c = '#1E40AF';
        return [
            ['name' => 'Agency — Project Proposal', 'category' => 'sales', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Custom Proposal') . $this->para('Hi {first_name}, thank you for the discussion. I have prepared a tailored proposal for your project.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Scope</span><strong style="color:' . self::H . ';">Website Redesign + CMS</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Timeline</span><strong style="color:' . self::H . ';">8 weeks</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Investment</span><strong style="color:' . $c . ';">$12,500</strong></div></div>'), $this->btn('View Full Proposal', $c), $this->txt('<p style="font-size:13px;color:' . self::M . ';">This proposal is valid for 14 days.</p>'), $this->ftr()]],
            ['name' => 'Agency — Project Kickoff', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Project Kickoff') . $this->para('Hi {first_name}, your project is officially underway. Here is what happens next.')), $this->txt($this->card('Week 1-2', 'Discovery and research phase. We will audit your current setup and define goals.', $c) . '<div style="height:8px;"></div>' . $this->card('Week 3-5', 'Design and prototyping. You will see wireframes and mockups for review.', '#10B981') . '<div style="height:8px;"></div>' . $this->card('Week 6-8', 'Development and testing. Your new system goes live.', '#F59E0B')), $this->btn('Access Project Portal', $c), $this->ftr()]],
            ['name' => 'Agency — Progress Update', 'category' => 'reporting', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Project Status Update') . $this->para('Hi {first_name}, here is your weekly progress update.')), $this->cols($this->metric('67%', 'Complete', '#10B981'), $this->metric('On Track', 'Status', $c)), $this->txt($this->listItem('Homepage design approved') . $this->listItem('CMS integration in progress') . $this->listItem('Content migration scheduled for next week')), $this->btn('View Project Board', $c), $this->ftr()]],
            ['name' => 'Agency — Invoice', 'category' => 'transactional', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Invoice #INV-2026-042') . $this->para('Hi {first_name}, please find your invoice below. Payment is due within 14 days.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Service</span><strong style="color:' . self::H . ';">Website Development</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Period</span><strong style="color:' . self::H . ';">March 2026</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Amount Due</span><strong style="color:#DC2626;">$4,500.00</strong></div></div>'), $this->btn('Pay Invoice', '#374151'), $this->ftr()]],
            ['name' => 'Agency — Invoice Reminder', 'category' => 'transactional', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('Payment Overdue') . $this->para('Hi {first_name}, invoice #INV-2026-042 is now 7 days past due. Please process payment at your earliest convenience.')), $this->btn('Pay Now', '#DC2626'), $this->ftr()]],
            ['name' => 'Agency — Client Onboarding', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Welcome Aboard, {first_name}') . $this->para('We are excited to work with you. To get started, please complete these three steps.')), $this->txt($this->card('1. Fill out the brief', 'Share your goals, brand guidelines, and preferences.', $c) . '<div style="height:8px;"></div>' . $this->card('2. Grant access', 'Share login credentials or invite us to your existing tools.', $c) . '<div style="height:8px;"></div>' . $this->card('3. Schedule kickoff', 'Pick a time for our first strategy call.', $c)), $this->btn('Complete Onboarding', $c), $this->ftr()]],
            ['name' => 'Agency — Testimonial Request', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Would You Recommend Us?') . $this->para('{first_name}, it has been great working with you. Would you be willing to share a brief testimonial? It takes about 2 minutes.')), $this->btn('Share Testimonial', $c), $this->ftr()]],
            ['name' => 'Agency — Contract Renewal', 'category' => 'sales', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Contract Is Up for Renewal') . $this->para('Hi {first_name}, your current agreement expires on April 30, 2026. Let us discuss next steps.')), $this->btn('Schedule a Call', $c), $this->ftr()]],
            ['name' => 'Agency — Case Study', 'category' => 'marketing', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">Case Study</p>' . $this->heading('How Acme Corp Increased Revenue 3x')), $this->cols($this->metric('3x', 'Revenue Growth', '#10B981'), $this->metric('47%', 'Cost Reduction', $c)), $this->txt($this->para('"Working with {company} transformed our business. The results speak for themselves." — Jane D., CEO')), $this->btn('Read Full Story', '#0F172A'), $this->ftr()]],
            ['name' => 'Agency — End of Engagement', 'category' => 'transactional', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Project Complete') . $this->para('Hi {first_name}, your project has been delivered. All assets and documentation are available in your portal. It has been a pleasure working with you.')), $this->btn('Access Deliverables', '#374151'), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  REAL ESTATE (8 templates)
    // =====================================================================
    private function realEstateTemplates(): array
    {
        $c = '#0D9488';
        return [
            ['name' => 'Real Estate — New Listing', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('New Property Match') . $this->para('Hi {first_name}, we found a property that matches your criteria.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Address</span><strong style="color:' . self::H . ';">742 Oak Avenue</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Bedrooms</span><strong style="color:' . self::H . ';">4 Bed / 3 Bath</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Price</span><strong style="color:' . $c . ';">$485,000</strong></div></div>'), $this->btn('View Property', $c), $this->ftr()]],
            ['name' => 'Real Estate — Open House', 'category' => 'events', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Open House This Saturday') . $this->para('You are invited to tour 742 Oak Avenue. Meet the agent, explore the property, and ask questions.')), $this->txt($this->card('Date', 'Saturday, April 5, 2026 — 10:00 AM to 2:00 PM', $c)), $this->btn('RSVP Now', $c), $this->ftr()]],
            ['name' => 'Real Estate — Market Report', 'category' => 'reporting', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">Market Update</p>' . $this->heading('Q1 2026 Market Report')), $this->cols($this->metric('$425K', 'Median Price', $c), $this->metric('18', 'Days on Market', '#3B82F6')), $this->cols($this->metric('+4.2%', 'YoY Growth', '#10B981'), $this->metric('1,247', 'Active Listings', '#F59E0B')), $this->btn('Full Report', '#0F172A'), $this->ftr()]],
            ['name' => 'Real Estate — Pre-Approval', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('You Are Pre-Approved') . $this->para('Congratulations, {first_name}! You have been pre-approved for a mortgage.')), $this->cols($this->metric('$450K', 'Approved Amount', $c), $this->metric('3.85%', 'Interest Rate', '#0F172A')), $this->btn('Start House Hunting', $c), $this->ftr()]],
            ['name' => 'Real Estate — Offer Accepted', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Offer Was Accepted') . $this->para('Great news, {first_name}! The seller has accepted your offer on 742 Oak Avenue. Here are the next steps.')), $this->txt($this->card('1. Home Inspection', 'Schedule within 10 days.', $c) . '<div style="height:8px;"></div>' . $this->card('2. Appraisal', 'Your lender will order this.', $c) . '<div style="height:8px;"></div>' . $this->card('3. Closing', 'Estimated date: May 15, 2026.', $c)), $this->ftr()]],
            ['name' => 'Real Estate — Closing Reminder', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Closing Day Is Coming') . $this->para('Your closing is scheduled for May 15, 2026. Please bring a valid photo ID and a cashier\'s check for the remaining balance.')), $this->btn('View Closing Checklist', $c), $this->ftr()]],
            ['name' => 'Real Estate — Anniversary', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Happy Home Anniversary') . $this->para('Hi {first_name}, it has been one year since you moved into your home. Hope you are loving it! If you ever need anything, I am here.')), $this->ftr()]],
            ['name' => 'Real Estate — Referral', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Know Someone Buying or Selling?') . $this->para('Refer a friend and receive a $500 gift card when they close on a property.')), $this->btn('Refer a Friend', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  HEALTHCARE (8 templates)
    // =====================================================================
    private function healthcareTemplates(): array
    {
        $c = '#0891B2';
        return [
            ['name' => 'Healthcare — Appointment Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Appointment Confirmed') . $this->para('Hi {first_name}, your appointment has been scheduled.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Doctor</span><strong style="color:' . self::H . ';">Dr. Sarah Mitchell</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Date</span><strong style="color:' . self::H . ';">April 3, 2026 at 10:30 AM</strong></div><div style="padding:6px 0;"><span style="color:' . self::M . ';">Location</span><strong style="color:' . self::H . ';"> 200 Medical Center Dr</strong></div></div>'), $this->btn('Add to Calendar', $c), $this->ftr()]],
            ['name' => 'Healthcare — Appointment Reminder', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Reminder: Tomorrow\'s Appointment') . $this->para('Hi {first_name}, this is a friendly reminder about your appointment tomorrow at 10:30 AM with Dr. Mitchell.')), $this->txt($this->card('Please bring', 'Photo ID, insurance card, and any relevant medical records.', $c)), $this->btn('Confirm Attendance', $c), $this->ftr()]],
            ['name' => 'Healthcare — Lab Results', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Lab Results Are Ready') . $this->para('Hi {first_name}, your recent lab work results are now available in your patient portal.')), $this->btn('View Results', $c), $this->txt('<p style="font-size:13px;color:' . self::M . ';">If you have questions about your results, please schedule a follow-up with your provider.</p>'), $this->ftr()]],
            ['name' => 'Healthcare — Wellness Check', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Time for Your Annual Checkup') . $this->para('{first_name}, it has been a year since your last visit. Regular checkups help catch issues early.')), $this->btn('Book Appointment', $c), $this->ftr()]],
            ['name' => 'Healthcare — Prescription Refill', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Prescription Refill Ready') . $this->para('Your prescription is ready for pickup at your pharmacy.')), $this->btn('View Details', $c), $this->ftr()]],
            ['name' => 'Healthcare — Patient Feedback', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('How Was Your Visit?') . $this->para('Hi {first_name}, we hope your recent visit went well. Your feedback helps us improve.')), $this->btn('Share Feedback', $c), $this->ftr()]],
            ['name' => 'Healthcare — Telehealth Invite', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Telehealth Session') . $this->para('Your virtual appointment starts at 2:00 PM today. Click the link below to join when ready.')), $this->btn('Join Video Call', $c), $this->ftr()]],
            ['name' => 'Healthcare — New Patient Welcome', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Welcome to Our Practice') . $this->para('Hi {first_name}, we are glad to have you as a patient. Please complete your intake forms before your first visit.')), $this->btn('Complete Intake Forms', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  FINANCE (8 templates)
    // =====================================================================
    private function financeTemplates(): array
    {
        $c = '#1E3A5F';
        return [
            ['name' => 'Finance — Account Statement', 'category' => 'reporting', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Monthly Statement') . $this->para('Hi {first_name}, your account statement for March 2026 is ready.')), $this->cols($this->metric('$12,450', 'Balance', $c), $this->metric('23', 'Transactions', '#6B7280')), $this->btn('View Full Statement', $c), $this->ftr()]],
            ['name' => 'Finance — Wire Transfer Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Transfer Confirmed') . $this->para('Your wire transfer of $5,000.00 has been processed successfully.')), $this->ftr()]],
            ['name' => 'Finance — Tax Document Ready', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Tax Documents Are Ready') . $this->para('Hi {first_name}, your 1099 form for the 2025 tax year is now available.')), $this->btn('Download Documents', $c), $this->ftr()]],
            ['name' => 'Finance — Investment Update', 'category' => 'reporting', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Portfolio Update')), $this->cols($this->metric('+8.4%', 'YTD Return', '#10B981'), $this->metric('$142K', 'Portfolio Value', $c)), $this->btn('View Portfolio', $c), $this->ftr()]],
            ['name' => 'Finance — Suspicious Activity', 'category' => 'transactional', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('Unusual Activity Detected') . $this->para('We noticed an unusual login attempt on your account. If this was not you, please secure your account immediately.')), $this->btn('Secure My Account', '#DC2626'), $this->ftr()]],
            ['name' => 'Finance — Loan Approval', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Loan Application Approved') . $this->para('Congratulations, {first_name}! Your loan has been approved.')), $this->cols($this->metric('$25,000', 'Amount', $c), $this->metric('5.2%', 'APR', '#6B7280')), $this->btn('Accept Loan', $c), $this->ftr()]],
            ['name' => 'Finance — Payment Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Payment Received') . $this->para('Your payment of $1,250.00 has been received and applied to your account. Thank you.')), $this->ftr()]],
            ['name' => 'Finance — Account Upgrade', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Upgrade to Premium Banking') . $this->para('Get higher interest rates, no fees, and priority support with our Premium account.')), $this->btn('Learn More', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  EDUCATION (8 templates)
    // =====================================================================
    private function educationTemplates(): array
    {
        $c = '#7C3AED';
        return [
            ['name' => 'Education — Course Enrollment', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Enrollment Confirmed') . $this->para('Hi {first_name}, you are enrolled in Advanced Data Science. Your course starts on April 7, 2026.')), $this->btn('Access Course', $c), $this->ftr()]],
            ['name' => 'Education — Assignment Reminder', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Assignment Due in 48 Hours') . $this->para('{first_name}, your assignment for Module 3 is due on Friday at 11:59 PM.')), $this->btn('Submit Assignment', $c), $this->ftr()]],
            ['name' => 'Education — Certificate', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('Congratulations, {first_name}!') . $this->para('You have completed Advanced Data Science with a score of 94%. Your certificate is ready.') . '</div>'), $this->btn('Download Certificate', $c), $this->ftr()]],
            ['name' => 'Education — New Course Available', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('New Course: Machine Learning Fundamentals') . $this->para('Based on your interests, you might enjoy our newest course. Starts May 1.')), $this->btn('Enroll Now', $c), $this->ftr()]],
            ['name' => 'Education — Progress Report', 'category' => 'reporting', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Learning Progress')), $this->cols($this->metric('78%', 'Course Complete', $c), $this->metric('A-', 'Current Grade', '#10B981')), $this->btn('Continue Learning', $c), $this->ftr()]],
            ['name' => 'Education — Webinar Invitation', 'category' => 'events', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Live Masterclass: AI in Business') . $this->para('Join our free 60-minute session on how AI is transforming modern business.')), $this->txt($this->card('When', 'April 12, 2026 at 2:00 PM EST', $c)), $this->btn('Register Free', $c), $this->ftr()]],
            ['name' => 'Education — Scholarship Available', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Scholarship Opportunity') . $this->para('Applications are now open for our 2026 merit-based scholarship. Covers 100% tuition for one course.')), $this->btn('Apply Now', $c), $this->ftr()]],
            ['name' => 'Education — Alumni Update', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Alumni Newsletter') . $this->para('Hi {first_name}, here is what is happening in our community this month.')), $this->btn('Read More', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  NONPROFIT (6 templates)
    // =====================================================================
    private function nonprofitTemplates(): array
    {
        $c = '#15803D';
        return [
            ['name' => 'Nonprofit — Donation Thank You', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Thank You for Your Donation') . $this->para('Hi {first_name}, your generous contribution of $100 makes a real difference. Here is how your donation will be used.')), $this->cols($this->metric('250', 'Meals Provided', $c), $this->metric('12', 'Families Helped', '#0F172A')), $this->btn('See Your Impact', $c), $this->ftr()]],
            ['name' => 'Nonprofit — Volunteer Opportunity', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Volunteer With Us') . $this->para('We have upcoming volunteer opportunities that match your interests. Join us in making a difference.')), $this->btn('Sign Up to Volunteer', $c), $this->ftr()]],
            ['name' => 'Nonprofit — Annual Report', 'category' => 'reporting', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">2025 Annual Report</p>' . $this->heading('Your Impact This Year')), $this->cols($this->metric('$1.2M', 'Raised', $c), $this->metric('45K', 'Lives Impacted', '#0F172A')), $this->btn('Read Full Report', '#0F172A'), $this->ftr()]],
            ['name' => 'Nonprofit — Event Invitation', 'category' => 'events', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Annual Fundraising Gala') . $this->para('You are invited to our annual gala dinner on May 20, 2026. Join us for an evening of celebration and giving.')), $this->btn('RSVP Now', $c), $this->ftr()]],
            ['name' => 'Nonprofit — Recurring Donation', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Make It Monthly') . $this->para('Your one-time donation changed lives. Imagine what a monthly contribution could do. Even $10/month helps.')), $this->btn('Start Monthly Giving', $c), $this->ftr()]],
            ['name' => 'Nonprofit — Tax Receipt', 'category' => 'transactional', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Donation Tax Receipt') . $this->para('This letter confirms your tax-deductible donation. Please keep this for your records.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Amount</span><strong style="color:' . self::H . ';">$500.00</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Date</span><strong style="color:' . self::H . ';">March 15, 2026</strong></div></div>'), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  HOSPITALITY (6 templates)
    // =====================================================================
    private function hospitalityTemplates(): array
    {
        $c = '#B45309';
        return [
            ['name' => 'Hospitality — Reservation Confirmation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Reservation Confirmed') . $this->para('Hi {first_name}, your table for 4 has been reserved.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Date</span><strong style="color:' . self::H . ';">Friday, April 4</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Time</span><strong style="color:' . self::H . ';">7:30 PM</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Guests</span><strong style="color:' . self::H . ';">4</strong></div></div>'), $this->btn('Modify Reservation', $c), $this->ftr()]],
            ['name' => 'Hospitality — Hotel Booking', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Booking Confirmed') . $this->para('Your stay at The Grand Hotel is confirmed. We look forward to welcoming you.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Check-in</span><strong style="color:' . self::H . ';">April 10, 2026</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Check-out</span><strong style="color:' . self::H . ';">April 13, 2026</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Room</span><strong style="color:' . self::H . ';">Deluxe King Suite</strong></div></div>'), $this->ftr()]],
            ['name' => 'Hospitality — Special Menu', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('This Week\'s Special Menu') . $this->para('Chef\'s seasonal selection featuring locally sourced ingredients.')), $this->btn('View Menu', $c), $this->ftr()]],
            ['name' => 'Hospitality — Loyalty Reward', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('You Earned a Reward') . $this->para('{first_name}, you have earned a complimentary dessert on your next visit. Just show this email.')), $this->ftr()]],
            ['name' => 'Hospitality — Post-Stay Feedback', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('How Was Your Stay?') . $this->para('We hope you enjoyed your time with us. Your feedback helps us improve.')), $this->btn('Share Feedback', $c), $this->ftr()]],
            ['name' => 'Hospitality — Event Booking', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Event Space Reserved') . $this->para('Your event booking for the Grand Ballroom on May 20, 2026 is confirmed. Capacity: 200 guests.')), $this->btn('View Event Details', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  FITNESS (6 templates)
    // =====================================================================
    private function fitnessTemplates(): array
    {
        $c = '#E11D48';
        return [
            ['name' => 'Fitness — Class Reminder', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Class Tomorrow: HIIT Bootcamp') . $this->para('{first_name}, your class is at 7:00 AM with Coach Mike. Bring water and a towel.')), $this->btn('View Schedule', $c), $this->ftr()]],
            ['name' => 'Fitness — Membership Welcome', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Welcome to the Team') . $this->para('Hi {first_name}, your membership is active. Here is how to get started.')), $this->txt($this->card('Download the App', 'Book classes, track workouts, and connect with trainers.', $c) . '<div style="height:8px;"></div>' . $this->card('Book Your First Class', 'Try our beginner-friendly sessions.', $c)), $this->btn('Get Started', $c), $this->ftr()]],
            ['name' => 'Fitness — Progress Update', 'category' => 'reporting', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Monthly Fitness Report')), $this->cols($this->metric('16', 'Workouts', $c), $this->metric('4,200', 'Calories Burned', '#F59E0B')), $this->btn('View Full Stats', $c), $this->ftr()]],
            ['name' => 'Fitness — Renewal Reminder', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Membership Renewal') . $this->para('Your membership expires on April 30. Renew now to keep your streak going.')), $this->btn('Renew Membership', $c), $this->ftr()]],
            ['name' => 'Fitness — Personal Training', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Take It to the Next Level') . $this->para('Ready for personalized coaching? Our trainers create custom programs for your goals.')), $this->btn('Book a Session', $c), $this->ftr()]],
            ['name' => 'Fitness — Challenge Invitation', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('30-Day Challenge Starts Monday') . $this->para('Join 500+ members in our spring fitness challenge. Daily workouts, community support, and prizes.')), $this->btn('Join the Challenge', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  RECRUITMENT / HR (6 templates)
    // =====================================================================
    private function recruitmentTemplates(): array
    {
        $c = '#4338CA';
        return [
            ['name' => 'Recruitment — Application Received', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Application Received') . $this->para('Hi {first_name}, we received your application for the Senior Developer position. Our team will review it within 5 business days.')), $this->ftr()]],
            ['name' => 'Recruitment — Interview Invitation', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Interview Invitation') . $this->para('We would like to invite you for an interview for the Senior Developer position.')), $this->txt($this->card('When', 'Thursday, April 10, 2026 at 2:00 PM EST', $c) . '<div style="height:8px;"></div>' . $this->card('Format', '45-minute video call via Zoom', $c)), $this->btn('Confirm Interview', $c), $this->ftr()]],
            ['name' => 'Recruitment — Offer Letter', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Job Offer: Senior Developer') . $this->para('Hi {first_name}, we are excited to extend an offer to join our team.')), $this->txt('<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:16px;font-size:14px;"><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Role</span><strong style="color:' . self::H . ';">Senior Developer</strong></div><div style="padding:6px 0;border-bottom:1px solid ' . self::BD . ';display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Salary</span><strong style="color:' . $c . ';">$120,000/year</strong></div><div style="padding:6px 0;display:flex;justify-content:space-between;"><span style="color:' . self::M . ';">Start Date</span><strong style="color:' . self::H . ';">May 1, 2026</strong></div></div>'), $this->btn('Review Full Offer', $c), $this->ftr()]],
            ['name' => 'Recruitment — Rejection', 'category' => 'transactional', 'blocks' => [$this->hdr('#6B7280'), $this->sp(), $this->txt($this->heading('Application Update') . $this->para('Hi {first_name}, thank you for your interest. After careful consideration, we have decided to move forward with other candidates. We encourage you to apply for future openings.')), $this->ftr()]],
            ['name' => 'Recruitment — New Employee Welcome', 'category' => 'onboarding', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Welcome to the Team, {first_name}!') . $this->para('We are thrilled to have you joining us. Here is what to expect on your first day.')), $this->txt($this->card('Check-in', 'Arrive at 9:00 AM. Ask for HR at reception.', $c) . '<div style="height:8px;"></div>' . $this->card('Setup', 'Your laptop and accounts will be ready.', $c) . '<div style="height:8px;"></div>' . $this->card('Team Lunch', 'Meet the team over lunch at noon.', $c)), $this->ftr()]],
            ['name' => 'Recruitment — Job Alert', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('New Position: Product Manager') . $this->para('A new role matching your profile just opened. Apply before April 15.')), $this->btn('View Job Details', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  LEGAL (4 templates)
    // =====================================================================
    private function legalTemplates(): array
    {
        $c = '#374151';
        return [
            ['name' => 'Legal — Document for Review', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Document Ready for Review') . $this->para('Hi {first_name}, a new document requires your review and signature. Please review it at your earliest convenience.')), $this->btn('Review Document', $c), $this->txt('<p style="font-size:13px;color:' . self::M . ';">This document expires on April 30, 2026.</p>'), $this->ftr()]],
            ['name' => 'Legal — Signature Requested', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Signature Required') . $this->para('A document is awaiting your electronic signature. Click below to review and sign.')), $this->btn('Sign Document', $c), $this->ftr()]],
            ['name' => 'Legal — Contract Executed', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Contract Fully Executed') . $this->para('All parties have signed the agreement. A copy has been attached to your account for your records.')), $this->btn('View Contract', $c), $this->ftr()]],
            ['name' => 'Legal — Privacy Policy Update', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Privacy Policy Update') . $this->para('We have updated our privacy policy effective April 1, 2026. The key changes relate to how we handle data processing and third-party integrations.')), $this->btn('Read Updated Policy', $c), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  TRANSACTIONAL / SYSTEM (8 templates)
    // =====================================================================
    private function transactionalTemplates(): array
    {
        $c = '#374151';
        return [
            ['name' => 'System — Email Verification', 'category' => 'transactional', 'blocks' => [$this->hdr('#4F46E5'), $this->sp(), $this->txt($this->heading('Verify Your Email') . $this->para('Hi {first_name}, please verify your email address by clicking the button below.')), $this->btn('Verify Email', '#4F46E5'), $this->txt('<p style="font-size:13px;color:' . self::M . ';">This link expires in 24 hours. If you did not create an account, please ignore this email.</p>'), $this->ftr()]],
            ['name' => 'System — Two-Factor Code', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Your Verification Code') . '<div style="text-align:center;margin:16px 0;"><p style="font-size:36px;font-weight:700;letter-spacing:8px;color:' . self::H . ';font-family:monospace;">482 917</p></div>' . $this->para('This code expires in 10 minutes. Do not share it with anyone.')), $this->ftr()]],
            ['name' => 'System — New Device Login', 'category' => 'transactional', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('New Login Detected') . $this->para('We noticed a login from a new device. If this was you, no action is needed.') . '<div style="background:' . self::BG . ';border:1px solid ' . self::BD . ';border-radius:8px;padding:12px;font-size:13px;margin-top:8px;"><div style="padding:4px 0;"><span style="color:' . self::M . ';">Device:</span> <span style="color:' . self::H . ';">Chrome on Windows</span></div><div style="padding:4px 0;"><span style="color:' . self::M . ';">Location:</span> <span style="color:' . self::H . ';">San Francisco, US</span></div><div style="padding:4px 0;"><span style="color:' . self::M . ';">Time:</span> <span style="color:' . self::H . ';">March 27, 2026 at 3:14 PM</span></div></div>'), $this->btn('Secure My Account', '#DC2626'), $this->ftr()]],
            ['name' => 'System — Account Deleted', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('Account Deletion Confirmed') . $this->para('Hi {first_name}, your account and all associated data have been permanently deleted as requested. We are sorry to see you go.')), $this->ftr()]],
            ['name' => 'System — Maintenance Notice', 'category' => 'transactional', 'blocks' => [$this->hdr('#D97706'), $this->sp(), $this->txt($this->heading('Scheduled Maintenance') . $this->para('We will be performing scheduled maintenance on April 5, 2026 from 2:00 AM to 4:00 AM UTC. The service may be briefly unavailable during this window.')), $this->ftr()]],
            ['name' => 'System — API Key Generated', 'category' => 'transactional', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('New API Key Created') . $this->para('A new API key was generated for your account. If you did not request this, please revoke it immediately from your settings.')), $this->btn('Manage API Keys', $c), $this->ftr()]],
            ['name' => 'System — Storage Warning', 'category' => 'transactional', 'blocks' => [$this->hdr('#D97706'), $this->sp(), $this->txt($this->heading('Storage Almost Full') . $this->para('You have used 90% of your storage quota. Consider upgrading your plan or deleting unused files.')), $this->btn('Manage Storage', '#D97706'), $this->ftr()]],
            ['name' => 'System — Weekly Digest', 'category' => 'reporting', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt($this->heading('Your Weekly Summary')), $this->cols($this->metric('47', 'Conversations', '#4F46E5'), $this->metric('12', 'Deals Closed', '#10B981')), $this->cols($this->metric('89%', 'Reply Rate', '#3B82F6'), $this->metric('$8.2K', 'Revenue', '#F59E0B')), $this->btn('View Dashboard', '#0F172A'), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  ENGAGEMENT / MARKETING (8 templates)
    // =====================================================================
    private function engagementTemplates(): array
    {
        $c = '#4F46E5';
        return [
            ['name' => 'Marketing — Newsletter', 'category' => 'newsletter', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<p style="margin:0 0 4px;font-size:12px;font-weight:600;color:' . $c . ';text-transform:uppercase;letter-spacing:1px;">This Week</p>' . $this->heading('The Weekly Brief') . $this->para('Your curated roundup of industry news, product updates, and practical tips.')), $this->div(), $this->txt($this->card('How to Automate 80% of Your Support', 'Learn the framework top teams use to handle support at scale.') . '<div style="height:8px;"></div>' . $this->card('5 Email Mistakes That Kill Open Rates', 'Common pitfalls and how to avoid them.', '#DC2626')), $this->btn('Read More on Our Blog', '#0F172A'), $this->ftr()]],
            ['name' => 'Marketing — Win-Back', 'category' => 'engagement', 'blocks' => [$this->hdr('#1F2937'), $this->sp(), $this->txt($this->heading('We Miss You, {first_name}') . $this->para('It has been a while since you logged in. A lot has changed and we think you will like what you see.')), $this->btn('See What\'s New', '#1F2937'), $this->ftr()]],
            ['name' => 'Marketing — Black Friday', 'category' => 'marketing', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('Black Friday Sale') . '<p style="font-size:40px;font-weight:800;color:#0F172A;margin:0;">40% OFF</p><p style="font-size:14px;color:' . self::M . ';margin:8px 0 0;">All annual plans. 48 hours only.</p></div>'), $this->btn('Claim Discount', '#0F172A'), $this->ftr()]],
            ['name' => 'Marketing — Customer Anniversary', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('Happy 1-Year Anniversary, {first_name}!') . $this->para('It has been an incredible year. Thank you for being part of our community.') . '</div>'), $this->cols($this->metric('1,247', 'Emails Sent', '#10B981'), $this->metric('342', 'AI Replies', $c)), $this->ftr()]],
            ['name' => 'Marketing — Early Access', 'category' => 'marketing', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('You\'re Invited: Beta Access') . $this->para('{first_name}, we are building something new and want your feedback. Join the beta program and be the first to try it.')), $this->btn('Join the Beta', $c), $this->ftr()]],
            ['name' => 'Marketing — Feedback Request', 'category' => 'engagement', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Quick Question, {first_name}') . $this->para('What is the one thing you wish {company} could do better? Your answer helps shape our roadmap. Reply directly to this email.')), $this->ftr()]],
            ['name' => 'Marketing — Partnership Inquiry', 'category' => 'sales', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt($this->heading('Partnership Opportunity') . $this->para('Hi {first_name}, I believe there is a strong fit between our companies. I would love to explore a potential partnership.')), $this->btn('Schedule a Call', '#0F172A'), $this->ftr()]],
            ['name' => 'Marketing — Seasonal Greeting', 'category' => 'engagement', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt('<div style="text-align:center;">' . $this->heading('Happy Holidays from {company}') . $this->para('Wishing you and your team a wonderful holiday season. Thank you for a great year together.') . '</div>'), $this->ftr()]],
        ];
    }

    // =====================================================================
    //  INTERNAL / TEAM (5 templates)
    // =====================================================================
    private function internalTemplates(): array
    {
        $c = '#4F46E5';
        return [
            ['name' => 'Internal — All-Hands Recap', 'category' => 'internal', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt($this->heading('All-Hands Meeting Recap') . $this->para('Here are the key takeaways from this week\'s all-hands meeting.')), $this->txt($this->card('Q1 Revenue', 'We hit 112% of our Q1 target. Great work, everyone.', '#10B981') . '<div style="height:8px;"></div>' . $this->card('New Hires', '8 new team members joining in April across Engineering and Sales.', $c) . '<div style="height:8px;"></div>' . $this->card('Product Update', 'Workflow 2.0 ships next week. QA is complete.', '#F59E0B')), $this->ftr()]],
            ['name' => 'Internal — Policy Update', 'category' => 'internal', 'blocks' => [$this->hdr('#374151'), $this->sp(), $this->txt($this->heading('Updated Remote Work Policy') . $this->para('Effective April 1, 2026, we are updating our remote work guidelines. Please review the changes and acknowledge by end of week.')), $this->btn('Review Policy', '#374151'), $this->ftr()]],
            ['name' => 'Internal — IT Security Alert', 'category' => 'internal', 'blocks' => [$this->hdr('#DC2626'), $this->sp(), $this->txt($this->heading('Security Alert: Password Reset Required') . $this->para('Due to a security review, all team members must reset their passwords by April 5, 2026. Please use a strong, unique password.')), $this->btn('Reset Password', '#DC2626'), $this->ftr()]],
            ['name' => 'Internal — New Tool Rollout', 'category' => 'internal', 'blocks' => [$this->hdr($c), $this->sp(), $this->txt($this->heading('New Tool: ' . config('app.name') . ' for Team Communication') . $this->para('Starting next week, all customer communication will go through ' . config('app.name') . '. Here is what you need to know.')), $this->txt($this->card('Training Session', 'Wednesday at 10 AM. Link will be shared in Slack.', $c) . '<div style="height:8px;"></div>' . $this->card('Your Account', 'You will receive a separate invite email with login details.', $c)), $this->ftr()]],
            ['name' => 'Internal — Employee Spotlight', 'category' => 'internal', 'blocks' => [$this->hdr('#0F172A'), $this->sp(), $this->txt($this->heading('Employee Spotlight: {first_name}') . $this->para('This month we are recognizing {first_name} for outstanding work on the Q1 product launch. Their dedication and leadership made a real impact on the team.')), $this->ftr()]],
        ];
    }
}
