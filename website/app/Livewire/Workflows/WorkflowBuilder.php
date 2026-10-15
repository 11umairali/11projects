<?php

namespace App\Livewire\Workflows;

use App\Exceptions\PlanLimitReachedException;
use App\Livewire\Campaigns\EmailTemplateGallery;
use App\Models\EmailTemplate;
use App\Models\Workflow;
use App\Models\WorkflowNode;
use App\Models\WorkflowEdge;
use App\Models\Workspace;
use App\Services\PlanLimitService;
use App\Models\ContactList;
use App\Traits\AuthorizesWorkspaceActions;
use Livewire\Component;

class WorkflowBuilder extends Component
{
    use AuthorizesWorkspaceActions;

    public ?int $workflowId = null;
    public int $currentStep = 1;

    // Step 1: Name + description
    public string $name = '';
    public string $description = '';

    // Step 2: Trigger
    public string $triggerType = '';
    public array $triggerConfig = [];

    // Step 3: Nodes
    public array $nodes = [];

    // Currently editing node
    public ?int $editingNodeIndex = null;

    // Undo / Redo stacks (session-only, not persisted to DB)
    public array $undoStack = [];
    public array $redoStack = [];
    public int $maxUndoLevels = 50;

    // ──────────────────────────────────────────────────────────
    // Guided Mode / Templates
    // ──────────────────────────────────────────────────────────

    public bool $showGuidedMode = false;
    public ?string $selectedTemplate = null;
    public int $guidedStep = 1;

    /** Pre-built workflow templates for the guided wizard. */
    public array $templates = [
        [
            'id' => 'customer-onboarding',
            'name' => 'Full Customer Onboarding',
            'description' => 'Complete 14-day onboarding sequence with welcome email, feature tours, check-ins, and engagement scoring',
            'icon' => 'rocket',
            'category' => 'email',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'contact_created', 'config' => ['label' => 'New Contact Signed Up']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Welcome Email', 'subject' => 'Welcome to {{company}}!']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: onboarding-started', 'tag_name' => 'onboarding-started']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 1 Day', 'duration' => 1, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Day 1: Getting Started Guide', 'subject' => 'Quick setup guide for {{first_name}}']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Days', 'duration' => 2, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Getting Started?', 'within_hours' => 48]],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Day 3: Top Features Tour', 'subject' => '3 features that save hours every week']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 4 Days', 'duration' => 4, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Day 7: Check-in & Tips', 'subject' => 'How\'s your first week going?']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => 'Add +20 Lead Score', 'field' => 'lead_score', 'value' => '20']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Lead Score > 50?', 'field' => 'lead_score', 'operator' => 'greater_than', 'value' => '50']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Day 14: Upgrade Invitation', 'subject' => 'Unlock premium features']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: onboarding-complete', 'tag_name' => 'onboarding-complete']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Notify Sales Team', 'message' => 'Contact completed onboarding', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'abandoned-cart-recovery',
            'name' => 'Abandoned Cart Recovery',
            'description' => 'Multi-touch cart recovery with escalating urgency, discount offers, and AI-powered re-engagement',
            'icon' => 'shopping-cart',
            'category' => 'email',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: cart-abandoned', 'tag_name' => 'cart-abandoned']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 1 Hour', 'duration' => 1, 'unit' => 'hours']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email 1: You Left Something Behind', 'subject' => 'You left something in your cart']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: recovery-email-1', 'tag_name' => 'recovery-email-1']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 24 Hours', 'duration' => 24, 'unit' => 'hours']],
                ['type' => 'condition', 'subtype' => 'has_tag', 'config' => ['label' => 'Still Has cart-abandoned?', 'tag_name' => 'cart-abandoned']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email 2: Items Selling Fast', 'subject' => 'Your saved items are selling fast']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 48 Hours', 'duration' => 48, 'unit' => 'hours']],
                ['type' => 'condition', 'subtype' => 'has_tag', 'config' => ['label' => 'Still Abandoned?', 'tag_name' => 'cart-abandoned']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email 3: 10% Discount Offer', 'subject' => 'Here\'s 10% off to complete your order']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => 'Set Field: discount_offered=true', 'field' => 'custom_fields.discount_offered', 'value' => 'true']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Alert Sales: High-Value Cart', 'message' => 'Cart abandoned 3x — needs personal outreach', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'lead-nurture-funnel',
            'name' => 'Lead Nurture & Qualification',
            'description' => 'Score leads based on engagement, qualify through multi-step content, and route hot leads to sales with Slack alerts',
            'icon' => 'star',
            'category' => 'crm',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'contact_created', 'config' => ['label' => 'New Lead Captured']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: lead-new', 'tag_name' => 'lead-new']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => 'Set Score: 10', 'field' => 'lead_score', 'value' => '10']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Educational Content #1', 'subject' => 'How top companies solve {{pain_point}}']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Email #1?', 'within_hours' => 72]],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+15 Lead Score', 'field' => 'lead_score', 'value' => '15']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Case Study Email', 'subject' => 'How {{company}} grew 3x with us']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 4 Days', 'duration' => 4, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Case Study Link?']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+25 Lead Score (Hot)', 'field' => 'lead_score', 'value' => '25']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: marketing-qualified', 'tag_name' => 'marketing-qualified']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Deal in Pipeline', 'title' => 'MQL: {{first_name}} {{last_name}}']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign to Sales Rep']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Hot Lead Alert', 'message' => 'New MQL ready for outreach: {{first_name}} (Score: {{lead_score}})', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Demo Booking Link', 'subject' => 'Let\'s schedule a quick call, {{first_name}}']],
            ],
        ],
        [
            'id' => 'deal-pipeline-automation',
            'name' => 'Full Deal Pipeline Automation',
            'description' => 'Automate your entire sales pipeline — from new deal to close — with stage-based emails, tasks, and team notifications',
            'icon' => 'trending-up',
            'category' => 'crm',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'deal_stage_changed', 'config' => ['label' => 'Deal Stage Changed']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Stage = Qualified?', 'field' => 'stage', 'operator' => 'equals', 'value' => 'qualified']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Intro & Pricing', 'subject' => 'Here\'s what {{company}} can do for you']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign Account Executive']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Days', 'duration' => 2, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Stage = Proposal?', 'field' => 'stage', 'operator' => 'equals', 'value' => 'proposal']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Custom Proposal', 'subject' => 'Your custom proposal is ready']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Proposal Sent', 'message' => 'Proposal sent for deal: {{deal_title}}', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Follow-Up: Decision Check', 'subject' => 'Any questions about the proposal?']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Deal Won?', 'field' => 'status', 'operator' => 'equals', 'value' => 'won']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Welcome & Onboarding', 'subject' => 'Welcome aboard! Here\'s your onboarding plan']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: customer', 'tag_name' => 'customer']],
                ['type' => 'action', 'subtype' => 'remove_tag', 'config' => ['label' => 'Remove: prospect', 'tag_name' => 'prospect']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Deal Won!', 'message' => 'Deal closed! {{deal_title}} — ${{deal_value}}', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'multi-channel-engagement',
            'name' => 'Multi-Channel Re-Engagement',
            'description' => 'Win back inactive contacts through email, SMS, and Slack over 30 days with progressive urgency and AI-powered replies',
            'icon' => 'zap',
            'category' => 'contacts',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: inactive-30d', 'tag_name' => 'inactive-30d']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email: We Miss You', 'subject' => 'It\'s been a while, {{first_name}}']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Re-Engage Email?', 'within_hours' => 72]],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+10 Lead Score', 'field' => 'lead_score', 'value' => '10']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email: What\'s New', 'subject' => 'See what you\'ve been missing']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Any Link?']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Email: Special Offer', 'subject' => 'Exclusive 20% off to welcome you back']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: re-engaged', 'tag_name' => 're-engaged']],
                ['type' => 'action', 'subtype' => 'remove_tag', 'config' => ['label' => 'Remove: inactive-30d', 'tag_name' => 'inactive-30d']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Notify: Contact Re-Engaged', 'message' => '{{first_name}} is back! Clicked re-engagement campaign', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Personal Follow-Up']],
            ],
        ],
        [
            'id' => 'customer-health-check',
            'name' => 'Customer Health & Churn Prevention',
            'description' => 'Monitor customer health scores, trigger rescue campaigns for at-risk accounts, and escalate to success team',
            'icon' => 'heart',
            'category' => 'crm',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Weekly Health Check (Monday 9 AM)', 'schedule' => 'weekly']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Lead Score < 20?', 'field' => 'lead_score', 'operator' => 'less_than', 'value' => '20']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: at-risk', 'tag_name' => 'at-risk']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Check-In Email', 'subject' => 'How can we help, {{first_name}}?']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign Customer Success Rep']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: At-Risk Alert', 'message' => 'At-risk customer: {{first_name}} (Score: {{lead_score}})', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Check-In?', 'within_hours' => 168]],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Value Reminder', 'subject' => 'Did you know you can do this?']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 More Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Score Still < 20?', 'field' => 'lead_score', 'operator' => 'less_than', 'value' => '20']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Final: Offer Free Strategy Call', 'subject' => 'Let\'s get you back on track — free strategy call']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Retention Deal', 'title' => 'Churn Risk: {{first_name}} {{last_name}}']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Escalate to Manager', 'message' => 'URGENT: Customer {{first_name}} at churn risk after 14-day rescue failed', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'webinar-funnel',
            'name' => 'Webinar Registration to Sale',
            'description' => 'Full webinar funnel — registration confirmation, reminders, post-event follow-up, replay, and conversion sequence',
            'icon' => 'video',
            'category' => 'email',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: webinar-registered', 'tag_name' => 'webinar-registered']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Confirmation + Calendar Link', 'subject' => 'You\'re registered! Save the date']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: funnel-webinar', 'tag_name' => 'funnel-webinar']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait Until Day Before', 'duration' => 6, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Reminder: Tomorrow!', 'subject' => 'Reminder: Live webinar is tomorrow']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 1 Day', 'duration' => 1, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Starting Now + Join Link', 'subject' => 'We\'re LIVE — join now']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Hours', 'duration' => 2, 'unit' => 'hours']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Replay + Resources', 'subject' => 'Missed it? Here\'s the replay']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 1 Day', 'duration' => 1, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Key Takeaways + Offer', 'subject' => '3 takeaways + exclusive offer inside']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+30 Lead Score', 'field' => 'lead_score', 'value' => '30']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Days', 'duration' => 2, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Offer Link?']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Final: Last Chance', 'subject' => 'Offer expires in 24 hours']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Hot Webinar Lead', 'message' => '{{first_name}} clicked offer link from webinar funnel', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'ecommerce-post-purchase',
            'name' => 'Post-Purchase Experience',
            'description' => 'Complete post-purchase flow — thank you, shipping, review request, cross-sell, and loyalty program enrollment',
            'icon' => 'gift',
            'category' => 'email',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: purchase-complete', 'tag_name' => 'purchase-complete']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Order Confirmation', 'subject' => 'Your order is confirmed!']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: customer', 'tag_name' => 'customer']],
                ['type' => 'action', 'subtype' => 'remove_tag', 'config' => ['label' => 'Remove: prospect', 'tag_name' => 'prospect']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Shipping Update', 'subject' => 'Your order is on its way!']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Ask for Review', 'subject' => 'How was your experience? Leave a review']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 14 Days', 'duration' => 14, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Cross-Sell Recommendation', 'subject' => 'Customers like you also love these']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+40 Lead Score (Buyer)', 'field' => 'lead_score', 'value' => '40']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 30 Days', 'duration' => 30, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Loyalty Program Invite', 'subject' => 'Join our VIP rewards program']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: loyalty-invited', 'tag_name' => 'loyalty-invited']],
            ],
        ],

        // =================================================================
        //  AI-POWERED TEMPLATES (20 complex, multi-branch, AI-connected)
        // =================================================================
        [
            'id' => 'ai-smart-triage',
            'name' => 'AI Smart Inbox Triage',
            'description' => 'AI analyses every incoming email — auto-replies to simple questions, routes complex ones to agents, flags VIPs, and escalates urgent issues',
            'icon' => 'brain',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'New Email Received']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Sentiment & Intent']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Is VIP Customer?', 'field' => 'tags', 'operator' => 'contains', 'value' => 'vip']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Senior Agent']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: VIP Email Alert', 'message' => 'VIP {{first_name}} sent an email — assigned to senior agent', 'channel' => 'slack']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Sentiment = Angry?', 'field' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: escalation-needed', 'tag_name' => 'escalation-needed']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Team Lead']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Urgent Escalation', 'message' => 'Angry customer needs immediate attention: {{first_name}}', 'channel' => 'slack']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'AI Confidence > 80%?', 'field' => 'ai_confidence', 'operator' => 'greater_than', 'value' => '80']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Auto-Send Reply']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: ai-handled', 'tag_name' => 'ai-handled']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+5 Lead Score (Engaged)', 'field' => 'lead_score', 'value' => '5']],
            ],
        ],
        [
            'id' => 'ai-sentiment-escalation',
            'name' => 'AI Sentiment-Based Escalation',
            'description' => 'Monitor all conversations — AI detects negative sentiment and auto-escalates to managers with context summary',
            'icon' => 'alert-triangle',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Any Inbound Message']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Sentiment']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Sentiment = Negative?', 'field' => 'sentiment', 'operator' => 'equals', 'value' => 'negative']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: unhappy-customer', 'tag_name' => 'unhappy-customer']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign to Customer Success']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Unhappy Customer', 'message' => 'Negative sentiment detected from {{first_name}} — routed to CS', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Empathetic Response']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Apology + Resolution', 'subject' => 'We hear you, {{first_name}} — here\'s what we\'re doing']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 24 Hours', 'duration' => 24, 'unit' => 'hours']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Follow-Up Check', 'subject' => 'Is everything resolved?']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+10 Score (Recovered)', 'field' => 'lead_score', 'value' => '10']],
            ],
        ],
        [
            'id' => 'ai-lead-scoring-multi',
            'name' => 'AI Multi-Signal Lead Scoring',
            'description' => 'Score leads from email opens, link clicks, page visits, and AI-analysed reply intent — auto-qualify and route to sales',
            'icon' => 'target',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'campaign_opened', 'config' => ['label' => 'Email Opened']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+5 Score (Opened)', 'field' => 'lead_score', 'value' => '5']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Any Link?']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+15 Score (Clicked)', 'field' => 'lead_score', 'value' => '15']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: engaged', 'tag_name' => 'engaged']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Score > 50?', 'field' => 'lead_score', 'operator' => 'greater_than', 'value' => '50']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Buying Intent']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: sales-qualified', 'tag_name' => 'sales-qualified']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Deal: SQL', 'title' => 'SQL: {{first_name}} {{last_name}}']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign Sales Rep']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Sales-Qualified Lead', 'message' => 'New SQL: {{first_name}} (Score: {{lead_score}})', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Personal Outreach Email', 'subject' => '{{first_name}}, let\'s chat about your goals']],
            ],
        ],
        [
            'id' => 'ai-auto-faq-handler',
            'name' => 'AI FAQ Auto-Responder',
            'description' => 'AI matches incoming questions to your Knowledge Base and auto-replies. Unmatched questions route to a human agent.',
            'icon' => 'book-open',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Support Email Received']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Search Knowledge Base']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'KB Match Found?', 'field' => 'ai_confidence', 'operator' => 'greater_than', 'value' => '75']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Send KB-Based Answer']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: ai-resolved', 'tag_name' => 'ai-resolved']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 2 Hours', 'duration' => 2, 'unit' => 'hours']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Follow-Up: Was This Helpful?', 'subject' => 'Did that answer your question?']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'No KB Match (Low Confidence)', 'field' => 'ai_confidence', 'operator' => 'less_than', 'value' => '50']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Human Agent']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Needs Human Help', 'message' => 'AI could not answer — routed to agent', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'ai-multilingual-support',
            'name' => 'AI Multilingual Auto-Reply',
            'description' => 'AI detects the language of incoming emails and replies in the same language using your Knowledge Base for context',
            'icon' => 'globe',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Inbound Email']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Detect Language']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Reply in Same Language']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Confidence > 70%?', 'field' => 'ai_confidence', 'operator' => 'greater_than', 'value' => '70']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: ai-multilingual', 'tag_name' => 'ai-multilingual']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Low Confidence?', 'field' => 'ai_confidence', 'operator' => 'less_than', 'value' => '50']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Native Speaker Agent']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Needs Translation Help', 'message' => 'Non-English email needs native speaker review', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'ai-churn-predictor',
            'name' => 'AI Churn Prediction & Rescue',
            'description' => 'AI monitors engagement patterns — predicts churn risk, triggers rescue campaigns, and escalates to success team before it is too late',
            'icon' => 'shield',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Daily at 8 AM', 'schedule' => 'daily']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'No Activity in 14 Days?', 'field' => 'last_seen_at', 'operator' => 'less_than', 'value' => '14_days_ago']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Calculate Churn Risk Score']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'High Churn Risk?', 'field' => 'churn_score', 'operator' => 'greater_than', 'value' => '70']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: churn-risk-high', 'tag_name' => 'churn-risk-high']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Personal Win-Back']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Personalised Re-Engagement', 'subject' => 'We noticed you have been away, {{first_name}}']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign Customer Success Rep']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Retention Deal', 'title' => 'Churn Risk: {{first_name}}']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Churn Risk Alert', 'message' => 'HIGH churn risk: {{first_name}} — inactive 14+ days', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Last Chance Offer', 'subject' => 'Before you go — a special offer for {{first_name}}']],
            ],
        ],
        [
            'id' => 'ai-review-and-approve',
            'name' => 'AI Draft + Human Approve',
            'description' => 'AI drafts replies for every inbound email — agent reviews, edits if needed, and approves before sending. Best for regulated industries.',
            'icon' => 'check-circle',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Inbound Email']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Draft Reply']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: ai-draft-pending', 'tag_name' => 'ai-draft-pending']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign to Reviewer']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Draft Ready for Review', 'message' => 'AI draft ready — review before sending to {{first_name}}', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 4 Hours (Approval Window)', 'duration' => 4, 'unit' => 'hours']],
                ['type' => 'condition', 'subtype' => 'has_tag', 'config' => ['label' => 'Still Pending?', 'tag_name' => 'ai-draft-pending']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Reminder — Draft Waiting', 'message' => 'AI draft for {{first_name}} has been waiting 4 hours', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'ai-smart-follow-up',
            'name' => 'AI Smart Follow-Up Sequence',
            'description' => 'AI generates personalised follow-ups based on conversation context — adjusts tone and timing based on engagement signals',
            'icon' => 'repeat',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Outbound Email Sent']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'No Reply in 3 Days?', 'within_hours' => 72]],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Follow-Up #1']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send AI Follow-Up', 'subject' => 'Quick follow-up, {{first_name}}']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Still No Reply?', 'within_hours' => 120]],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Follow-Up #2 (New Angle)']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Value-Add Follow-Up', 'subject' => 'Thought you might find this useful']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 7 Days', 'duration' => 7, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Breakup Email']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Final: Closing the Loop', 'subject' => 'Should I close this out?']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: sequence-complete', 'tag_name' => 'follow-up-complete']],
            ],
        ],
        [
            'id' => 'ai-sales-outreach',
            'name' => 'AI Cold Outreach Campaign',
            'description' => 'AI researches each prospect, generates personalised outreach, and adapts follow-ups based on engagement — fully automated prospecting',
            'icon' => 'mail',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: prospect-new', 'tag_name' => 'prospect-new']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Research Prospect & Company']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Personalised Intro Email']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Cold Email #1', 'subject' => '{{first_name}}, quick question about {{company}}']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 4 Days', 'duration' => 4, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Email?', 'within_hours' => 96]],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Follow-Up with Social Proof']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Follow-Up with Case Study', 'subject' => 'How we helped a company like {{company}}']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+20 Lead Score', 'field' => 'lead_score', 'value' => '20']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Case Study?']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Deal', 'title' => 'Outreach: {{first_name}} @ {{company}}']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Hot Prospect', 'message' => '{{first_name}} clicked case study — deal created', 'channel' => 'slack']],
            ],
        ],
        [
            'id' => 'ai-meeting-prep',
            'name' => 'AI Meeting Prep & Follow-Up',
            'description' => 'AI prepares briefing notes before meetings and generates follow-up emails with action items afterwards',
            'icon' => 'calendar',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: meeting-scheduled', 'tag_name' => 'meeting-scheduled']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Meeting Briefing']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Meeting Prep Ready', 'message' => 'AI briefing for {{first_name}} meeting is ready', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Confirmation + Agenda', 'subject' => 'Confirmed: Our call on Thursday']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait Until After Meeting', 'duration' => 1, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Follow-Up with Action Items']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Meeting Summary', 'subject' => 'Meeting recap and next steps']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: meeting-complete', 'tag_name' => 'meeting-complete']],
                ['type' => 'action', 'subtype' => 'remove_tag', 'config' => ['label' => 'Remove: meeting-scheduled', 'tag_name' => 'meeting-scheduled']],
            ],
        ],
        [
            'id' => 'ai-support-categoriser',
            'name' => 'AI Support Ticket Categoriser',
            'description' => 'AI reads every support email, categorises it (billing, technical, feature request, bug), assigns priority, and routes to the right team',
            'icon' => 'layers',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Support Email Received']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Categorise & Prioritise']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Category = Billing?', 'field' => 'category', 'operator' => 'equals', 'value' => 'billing']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: billing-issue', 'tag_name' => 'billing-issue']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Billing Team']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Category = Bug?', 'field' => 'category', 'operator' => 'equals', 'value' => 'bug']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: bug-report', 'tag_name' => 'bug-report']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Route to Engineering']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Bug Report Filed', 'message' => 'New bug report from {{first_name}} — routed to engineering', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Send Acknowledgement']],
            ],
        ],
        [
            'id' => 'ai-deal-intelligence',
            'name' => 'AI Deal Intelligence',
            'description' => 'AI analyses every deal conversation — extracts key info, updates deal fields, predicts close probability, and alerts sales managers',
            'icon' => 'bar-chart',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'deal_stage_changed', 'config' => ['label' => 'Deal Stage Updated']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Deal Conversations']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Predict Close Probability']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Close Probability > 80%?', 'field' => 'win_probability', 'operator' => 'greater_than', 'value' => '80']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Deal Likely to Close', 'message' => 'Deal "{{deal_title}}" has 80%+ close probability', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: high-probability', 'tag_name' => 'high-probability-deal']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Close Probability < 30%?', 'field' => 'win_probability', 'operator' => 'less_than', 'value' => '30']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Deal At Risk', 'message' => 'Deal "{{deal_title}}" at risk — needs attention', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Escalate to Sales Manager']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Suggest Next Best Action']],
            ],
        ],
        [
            'id' => 'ai-content-personaliser',
            'name' => 'AI Content Personalisation',
            'description' => 'AI selects the best content for each contact based on their industry, role, and engagement history — fully personalised nurture',
            'icon' => 'edit',
            'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: nurture-start', 'tag_name' => 'nurture-start']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Contact Profile']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Select Best Content Piece']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Personalised Content', 'subject' => '{{first_name}}, I thought you would find this relevant']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_clicked', 'config' => ['label' => 'Clicked Content?']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+20 Score', 'field' => 'lead_score', 'value' => '20']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Select Next Best Content']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Next Content Piece', 'subject' => 'Another resource you might like']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 5 Days', 'duration' => 5, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send CTA: Book a Demo', 'subject' => 'Ready to see it in action?']],
            ],
        ],
        [
            'id' => 'ai-feedback-analyser', 'name' => 'AI Customer Feedback Loop', 'description' => 'Collect feedback via email, AI analyses themes and sentiment, routes insights to product team, and closes the loop with the customer', 'icon' => 'message-circle', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'tag_added', 'config' => ['label' => 'Tagged: feedback-received', 'tag_name' => 'feedback-received']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Feedback Themes']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Categorise (Bug/Feature/Praise/Complaint)']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Feedback Summary to Product', 'message' => 'New feedback from {{first_name}}: AI-categorised and summarised', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Thank You for Feedback', 'subject' => 'We heard you, {{first_name}} — thank you']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: feedback-processed', 'tag_name' => 'feedback-processed']],
            ],
        ],
        [
            'id' => 'ai-competitor-response', 'name' => 'AI Competitor Mention Handler', 'description' => 'When a contact mentions a competitor, AI crafts a comparison response highlighting your advantages', 'icon' => 'crosshair', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'email_received', 'config' => ['label' => 'Email Mentioning Competitor']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Detect Competitor Mention']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: competitor-mention', 'tag_name' => 'competitor-mention']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Comparison Response']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Assign to Sales Rep']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Competitor Alert', 'message' => '{{first_name}} mentioned a competitor — AI draft ready', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => 'Flag: Evaluating Alternatives', 'field' => 'custom_fields.evaluating', 'value' => 'true']],
            ],
        ],
        [
            'id' => 'ai-upsell-cross-sell', 'name' => 'AI Upsell & Cross-Sell', 'description' => 'AI analyses purchase history and usage patterns to recommend upgrades and additional products at the optimal moment', 'icon' => 'trending-up', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Monthly Check (1st of Month)', 'schedule' => 'monthly']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Analyse Usage & Purchase Patterns']],
                ['type' => 'condition', 'subtype' => 'contact_field', 'config' => ['label' => 'Usage > 80% of Plan Limit?', 'field' => 'usage_percent', 'operator' => 'greater_than', 'value' => '80']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Generate Upgrade Recommendation']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Personalised Upgrade Email', 'subject' => 'You are outgrowing your plan, {{first_name}}']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: upsell-candidate', 'tag_name' => 'upsell-candidate']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: Upsell Opportunity', 'message' => '{{first_name}} at 80%+ usage — upgrade opportunity', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'create_deal', 'config' => ['label' => 'Create Upsell Deal', 'title' => 'Upsell: {{first_name}} — Plan Upgrade']],
            ],
        ],
        [
            'id' => 'ai-onboarding-adaptive', 'name' => 'AI Adaptive Onboarding', 'description' => 'AI tracks which features each user has tried and sends personalised tips for features they have not discovered yet', 'icon' => 'compass', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'contact_created', 'config' => ['label' => 'New User Signed Up']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Welcome Email', 'subject' => 'Welcome to {{company}}!']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Check Feature Adoption']],
                ['type' => 'condition', 'subtype' => 'has_tag', 'config' => ['label' => 'Has Not Used AI Replies?', 'tag_name' => 'used-ai-replies']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Tip: Try AI Replies', 'subject' => 'Did you know AI can reply for you?']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Check Next Unused Feature']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Tip: Next Feature to Try', 'subject' => 'Unlock more value from {{company}}']],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+15 Score (Onboarding Progress)', 'field' => 'lead_score', 'value' => '15']],
            ],
        ],
        [
            'id' => 'ai-sla-monitor', 'name' => 'AI SLA Monitor & Auto-Escalate', 'description' => 'Monitor response times — AI escalates conversations approaching SLA breach and auto-drafts responses to prevent violations', 'icon' => 'clock', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Every 30 Minutes', 'schedule' => 'every_30min']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Unanswered > 2 Hours?', 'field' => 'response_time', 'operator' => 'greater_than', 'value' => '120']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Draft Quick Response']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: SLA Warning', 'message' => 'SLA breach approaching — conversation unanswered for 2+ hours', 'channel' => 'slack']],
                ['type' => 'condition', 'subtype' => 'if_else', 'config' => ['label' => 'Unanswered > 4 Hours?', 'field' => 'response_time', 'operator' => 'greater_than', 'value' => '240']],
                ['type' => 'action', 'subtype' => 'assign_agent', 'config' => ['label' => 'Escalate to Team Lead']],
                ['type' => 'action', 'subtype' => 'send_notification', 'config' => ['label' => 'Slack: SLA BREACH', 'message' => 'SLA BREACHED — conversation unanswered 4+ hours. Escalated.', 'channel' => 'slack']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: sla-breached', 'tag_name' => 'sla-breached']],
            ],
        ],
        [
            'id' => 'ai-newsletter-curator', 'name' => 'AI Newsletter Content Curator', 'description' => 'AI selects the best-performing content from your Knowledge Base and composes a personalised newsletter for each segment', 'icon' => 'file-text', 'category' => 'ai',
            'nodes' => [
                ['type' => 'trigger', 'subtype' => 'scheduled', 'config' => ['label' => 'Weekly (Friday 10 AM)', 'schedule' => 'weekly']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Select Top Content from KB']],
                ['type' => 'action', 'subtype' => 'ai_reply', 'config' => ['label' => 'AI: Write Newsletter Copy']],
                ['type' => 'action', 'subtype' => 'send_email', 'config' => ['label' => 'Send Weekly Newsletter', 'subject' => 'This week\'s top reads for you']],
                ['type' => 'action', 'subtype' => 'add_tag', 'config' => ['label' => 'Tag: newsletter-sent', 'tag_name' => 'newsletter-sent-week']],
                ['type' => 'action', 'subtype' => 'wait_delay', 'config' => ['label' => 'Wait 3 Days', 'duration' => 3, 'unit' => 'days']],
                ['type' => 'condition', 'subtype' => 'email_opened', 'config' => ['label' => 'Opened Newsletter?', 'within_hours' => 72]],
                ['type' => 'action', 'subtype' => 'update_contact', 'config' => ['label' => '+5 Score (Newsletter Reader)', 'field' => 'lead_score', 'value' => '5']],
            ],
        ],
    ];

    // Available trigger types
    public array $triggerTypes = [
        'email_received' => 'New Email Received',
        'contact_created' => 'Contact Created',
        'contact_updated' => 'Contact Updated',
        'deal_stage_changed' => 'Deal Stage Changed',
        'tag_added' => 'Tag Added',
        'tag_removed' => 'Tag Removed',
        'campaign_opened' => 'Campaign Opened',
        'campaign_clicked' => 'Campaign Link Clicked',
        'form_submitted' => 'Form Submitted',
        'webhook_received' => 'Webhook',
        'scheduled' => 'Scheduled / Recurring',
    ];

    /**
     * Kept as an empty list so the save-path validator stays wired in case we
     * ever need to gate a newly-added trigger again before its backend lands.
     */
    protected array $unimplementedTriggers = [];

    // Available node subtypes
    public array $actionSubtypes = [
        'send_email' => 'Send Email',
        'send_notification' => 'Send Notification',
        'add_tag' => 'Add Tag',
        'remove_tag' => 'Remove Tag',
        'update_contact' => 'Update Contact Field',
        'assign_agent' => 'Assign to Agent',
        'create_deal' => 'Create Deal',
        'move_deal' => 'Move Deal Stage',
        'wait_delay' => 'Wait / Delay',
        'webhook_call' => 'Send data to external app (Webhook)',
        'ai_reply' => 'Generate AI Reply',
        'add_to_group' => 'Add to Group',
        'remove_from_group' => 'Remove from Group',
        'create_calendar_event' => 'Create Calendar Event',
    ];

    public array $conditionSubtypes = [
        'if_else' => 'If / Else',
        'has_tag' => 'Has Tag',
        'contact_field' => 'Contact Field Check',
        'email_opened' => 'Email Opened',
        'email_clicked' => 'Email Clicked',
        'in_group' => 'Is in Group',
    ];

    // Whitelist of allowed config keys per node subtype
    protected array $allowedConfigKeys = [
        'send_email' => ['subject', 'body', 'body_html', 'from_name', 'email_template_id'],
        'send_notification' => ['message', 'channel', 'title', 'email', 'icon', 'action_url', 'notify_user_ids'],
        'add_tag' => ['tag_name'],
        'remove_tag' => ['tag_name'],
        'has_tag' => ['tag_name'],
        'update_contact' => ['field', 'value'],
        'assign_agent' => ['agent_id', 'round_robin'],
        'create_deal' => ['deal_name', 'pipeline_id', 'value'],
        'move_deal' => ['stage_id'],
        'wait_delay' => ['duration', 'unit'],
        'webhook_call' => ['url', 'method'],
        'ai_reply' => ['instructions', 'max_tokens'],
        'create_calendar_event' => ['summary', 'description', 'location', 'start_offset_minutes', 'duration_minutes', 'attendees', 'timezone'],
        'if_else' => ['field', 'operator', 'value'],
        'contact_field' => ['field', 'operator', 'value'],
        'email_opened' => ['within_hours'],
        'email_clicked' => ['within_hours'],
        'add_to_group' => ['group_id'],
        'remove_from_group' => ['group_id'],
        'in_group' => ['group_id'],
    ];

    public function mount(?int $workflowId = null): void
    {
        $this->workflowId = $workflowId;

        // Open guided mode when arriving via ?guided=1
        if (!$workflowId && request()->boolean('guided')) {
            $this->showGuidedMode = true;
        }

        if ($workflowId) {
            $workflow = Workflow::where('workspace_id', $this->workspaceId())
                ->with(['workflowNodes' => function ($q) {
                    $q->orderBy('position_y');
                }])
                ->findOrFail($workflowId);

            $this->name = $workflow->name;
            $this->description = $workflow->description ?? '';

            // Load trigger
            $triggerNode = $workflow->workflowNodes->firstWhere('type', 'trigger');
            $this->triggerType = $triggerNode?->subtype ?? '';
            $this->triggerConfig = $triggerNode?->config ?? [];

            // Load action/condition nodes
            $this->nodes = $workflow->workflowNodes
                ->where('type', '!=', 'trigger')
                ->values()
                ->map(function ($node) {
                    return [
                        'id' => $node->id,
                        'type' => $node->type,
                        'subtype' => $node->subtype,
                        'config' => $node->config ?? [],
                    ];
                })->toArray();
        }
    }

    public function goToStep(int $step): void
    {
        // Validate current step before advancing
        if ($step > $this->currentStep) {
            if ($this->currentStep === 1) {
                $this->validate([
                    'name' => 'required|string|max:255',
                ]);
            } elseif ($this->currentStep === 2) {
                $this->validate([
                    'triggerType' => 'required|string',
                ]);
            }
        }

        $this->currentStep = $step;
    }

    // ──────────────────────────────────────────────────────────
    // Undo / Redo — command-pattern state stack
    // ──────────────────────────────────────────────────────────

    /**
     * Capture a snapshot of the current canvas state before any mutation.
     * Must be called as the FIRST line inside every method that modifies
     * nodes, triggerType, or triggerConfig.
     */
    protected function pushState(): void
    {
        $this->undoStack[] = [
            'nodes'            => $this->nodes,
            'triggerType'      => $this->triggerType,
            'triggerConfig'    => $this->triggerConfig,
            'editingNodeIndex' => $this->editingNodeIndex,
        ];

        // New action invalidates the redo history
        $this->redoStack = [];

        // Trim oldest entries if we exceed the cap
        if (count($this->undoStack) > $this->maxUndoLevels) {
            $this->undoStack = array_slice($this->undoStack, -$this->maxUndoLevels);
        }
    }

    /**
     * Revert to the previous canvas state.
     */
    public function undo(): void
    {
        if (empty($this->undoStack)) {
            return;
        }

        // Save current state so it can be re-done
        $this->redoStack[] = [
            'nodes'            => $this->nodes,
            'triggerType'      => $this->triggerType,
            'triggerConfig'    => $this->triggerConfig,
            'editingNodeIndex' => $this->editingNodeIndex,
        ];

        $previous = array_pop($this->undoStack);
        $this->nodes            = $previous['nodes'];
        $this->triggerType      = $previous['triggerType'];
        $this->triggerConfig    = $previous['triggerConfig'];
        $this->editingNodeIndex = $previous['editingNodeIndex'];

        $this->dispatch('canvas-state-restored', action: 'undo');
    }

    /**
     * Re-apply a previously undone state.
     */
    public function redo(): void
    {
        if (empty($this->redoStack)) {
            return;
        }

        // Save current state so it can be un-done again
        $this->undoStack[] = [
            'nodes'            => $this->nodes,
            'triggerType'      => $this->triggerType,
            'triggerConfig'    => $this->triggerConfig,
            'editingNodeIndex' => $this->editingNodeIndex,
        ];

        $next = array_pop($this->redoStack);
        $this->nodes            = $next['nodes'];
        $this->triggerType      = $next['triggerType'];
        $this->triggerConfig    = $next['triggerConfig'];
        $this->editingNodeIndex = $next['editingNodeIndex'];

        $this->dispatch('canvas-state-restored', action: 'redo');
    }

    /**
     * Whether there is at least one state to undo to.
     */
    public function canUndo(): bool
    {
        return count($this->undoStack) > 0;
    }

    /**
     * Whether there is at least one state to redo.
     */
    public function canRedo(): bool
    {
        return count($this->redoStack) > 0;
    }

    // ──────────────────────────────────────────────────────────
    // Node mutations
    // ──────────────────────────────────────────────────────────

    public function addNode(string $type, string $subtype): void
    {
        // Validate type and subtype before capturing state
        $validTypes = ['action', 'condition'];
        if (!in_array($type, $validTypes)) return;

        $allSubtypes = array_merge(array_keys($this->actionSubtypes), array_keys($this->conditionSubtypes));
        if (!in_array($subtype, $allSubtypes)) return;

        $this->pushState();

        $this->nodes[] = [
            'id' => null,
            'type' => $type,
            'subtype' => $subtype,
            'config' => $this->getDefaultConfig($subtype),
        ];

        $newIndex = count($this->nodes) - 1;
        $this->editingNodeIndex = $newIndex;

        // Dispatch browser event so Alpine can scroll to and flash the new node
        $this->dispatch('node-added', index: $newIndex);
    }

    /**
     * Insert a node AFTER the given index (used by inline "+" buttons between nodes).
     */
    public function insertNodeAfter(int $afterIndex, string $type, string $subtype): void
    {
        $validTypes = ['action', 'condition'];
        if (!in_array($type, $validTypes)) return;

        $allSubtypes = array_merge(array_keys($this->actionSubtypes), array_keys($this->conditionSubtypes));
        if (!in_array($subtype, $allSubtypes)) return;

        $this->pushState();

        $insertAt = min($afterIndex + 1, count($this->nodes));

        $newNode = [
            'id' => null,
            'type' => $type,
            'subtype' => $subtype,
            'config' => $this->getDefaultConfig($subtype),
        ];

        array_splice($this->nodes, $insertAt, 0, [$newNode]);
        $this->nodes = array_values($this->nodes);
        $this->editingNodeIndex = $insertAt;

        $this->dispatch('node-added', index: $insertAt);
    }

    public function removeNode(int $index): void
    {
        if (!isset($this->nodes[$index])) return;

        $this->pushState();

        array_splice($this->nodes, $index, 1);
        $this->nodes = array_values($this->nodes);
        $this->editingNodeIndex = null;
    }

    public function editNode(int $index): void
    {
        $this->editingNodeIndex = $this->editingNodeIndex === $index ? null : $index;
    }

    public function moveNodeUp(int $index): void
    {
        if ($index > 0) {
            $this->pushState();

            $temp = $this->nodes[$index - 1];
            $this->nodes[$index - 1] = $this->nodes[$index];
            $this->nodes[$index] = $temp;

            // Update editing index if applicable
            if ($this->editingNodeIndex === $index) {
                $this->editingNodeIndex = $index - 1;
            } elseif ($this->editingNodeIndex === $index - 1) {
                $this->editingNodeIndex = $index;
            }
        }
    }

    public function moveNodeDown(int $index): void
    {
        if ($index < count($this->nodes) - 1) {
            $this->pushState();

            $temp = $this->nodes[$index + 1];
            $this->nodes[$index + 1] = $this->nodes[$index];
            $this->nodes[$index] = $temp;

            // Update editing index if applicable
            if ($this->editingNodeIndex === $index) {
                $this->editingNodeIndex = $index + 1;
            } elseif ($this->editingNodeIndex === $index + 1) {
                $this->editingNodeIndex = $index;
            }
        }
    }

    protected function getDefaultConfig(string $subtype): array
    {
        return match($subtype) {
            'send_email' => ['subject' => '', 'body' => '', 'body_html' => '', 'from_name' => '', 'email_template_id' => ''],
            'send_notification' => ['message' => '', 'channel' => 'in_app', 'title' => 'Workflow Notification', 'email' => '', 'icon' => 'bell', 'action_url' => ''],
            'add_tag', 'remove_tag', 'has_tag' => ['tag_name' => ''],
            'add_to_group', 'remove_from_group', 'in_group' => ['group_id' => ''],
            'update_contact' => ['field' => '', 'value' => ''],
            'assign_agent' => ['agent_id' => '', 'round_robin' => false],
            'create_deal' => ['deal_name' => '', 'pipeline_id' => '', 'value' => ''],
            'move_deal' => ['stage_id' => ''],
            'wait_delay' => ['duration' => 1, 'unit' => 'hours'],
            'webhook_call' => ['url' => '', 'method' => 'POST'],
            'ai_reply' => ['instructions' => '', 'max_tokens' => 500],
            'if_else' => ['field' => '', 'operator' => 'equals', 'value' => ''],
            'contact_field' => ['field' => '', 'operator' => 'equals', 'value' => ''],
            'email_opened', 'email_clicked' => ['within_hours' => 24],
            default => [],
        };
    }

    /**
     * Sanitize node config: only allow whitelisted keys per subtype.
     */
    protected function sanitizeNodeConfig(string $subtype, array $config): array
    {
        $allowed = $this->allowedConfigKeys[$subtype] ?? [];
        if (empty($allowed)) {
            return [];
        }
        return array_intersect_key($config, array_flip($allowed));
    }

    /**
     * Persist trigger + action/condition nodes and edges to DB.
     */
    protected function persistNodesAndEdges(Workflow $workflow): void
    {
        // Remove old nodes and edges
        $workflow->workflowEdges()->delete();
        $workflow->workflowNodes()->delete();

        // Create trigger node with config
        $triggerNode = WorkflowNode::create([
            'workflow_id' => $workflow->id,
            'type' => 'trigger',
            'subtype' => $this->triggerType,
            'config' => $this->triggerConfig,
            'position_x' => 0,
            'position_y' => 0,
        ]);

        // Create action/condition nodes and edges
        $previousNodeId = $triggerNode->id;
        foreach ($this->nodes as $index => $nodeData) {
            $sanitizedConfig = $this->sanitizeNodeConfig($nodeData['subtype'], $nodeData['config'] ?? []);

            $node = WorkflowNode::create([
                'workflow_id' => $workflow->id,
                'type' => $nodeData['type'],
                'subtype' => $nodeData['subtype'],
                'config' => $sanitizedConfig,
                'position_x' => 0,
                'position_y' => ($index + 1) * 100,
            ]);

            // Create edge from previous node to this one
            WorkflowEdge::create([
                'workflow_id' => $workflow->id,
                'from_node_id' => $previousNodeId,
                'to_node_id' => $node->id,
                'label' => 'default',
            ]);

            $previousNodeId = $node->id;
        }

        // Save canvas data as JSON snapshot
        $workflow->update([
            'canvas_data' => [
                'trigger' => $this->triggerType,
                'trigger_config' => $this->triggerConfig,
                'nodes' => $this->nodes,
            ],
        ]);
    }

    public function save(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        $this->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'triggerType' => 'required|string',
        ]);

        // Validate trigger type is in whitelist
        if (!array_key_exists($this->triggerType, $this->triggerTypes)) {
            $this->addError('triggerType', 'Invalid trigger type.');
            return;
        }

        // Block saving with a trigger that has no backend dispatch yet.
        // The dropdown keeps them visible for the roadmap, but a workflow
        // saved with one would silently never fire — better to fail loud.
        if (in_array($this->triggerType, $this->unimplementedTriggers, true)) {
            $this->addError('triggerType', 'This trigger is not available yet. Please pick another.');
            return;
        }

        $workspaceId = $this->workspaceId();

        // Enforce plan limit when creating a new workflow (not editing existing)
        if (! $this->workflowId) {
            try {
                $workspace = Workspace::findOrFail($workspaceId);
                app(PlanLimitService::class)->assertCanCreate($workspace, 'workflows');
            } catch (PlanLimitReachedException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        if ($this->workflowId) {
            $workflow = Workflow::where('workspace_id', $workspaceId)
                ->findOrFail($this->workflowId);
            $workflow->update([
                'name' => $this->name,
                'description' => $this->description,
                'version' => $workflow->version + 1,
            ]);
        } else {
            $workflow = Workflow::create([
                'workspace_id' => $workspaceId,
                'created_by' => auth()->id(),
                'name' => $this->name,
                'description' => $this->description,
                'status' => 'draft',
            ]);
            $this->workflowId = $workflow->id;
        }

        $this->persistNodesAndEdges($workflow);
        $this->ensureWebhookToken($workflow);

        session()->flash('success', 'Workflow saved successfully.');
        $this->redirect(route('workflows'), navigate: true);
    }

    /**
     * Ensure the workflow has a unique webhook_token when its trigger is
     * `webhook_received`. Idempotent — only generates on first save with
     * this trigger, keeps the existing token on edits so inbound callers
     * don't break.
     */
    protected function ensureWebhookToken(Workflow $workflow): void
    {
        if ($this->triggerType !== 'webhook_received') return;
        if ($workflow->webhook_token) return;

        // 40 random alphanumeric chars — enough entropy to be unguessable,
        // short enough to fit cleanly in a URL path segment.
        $workflow->update([
            'webhook_token' => \Illuminate\Support\Str::random(40),
        ]);
    }

    public function activate(): void
    {
        if (!$this->authorizeWorkspaceAction('manage')) return;

        // Validate before saving
        $this->validate([
            'name' => 'required|string|max:255',
            'triggerType' => 'required|string',
        ]);

        if (!array_key_exists($this->triggerType, $this->triggerTypes)) {
            $this->addError('triggerType', 'Invalid trigger type.');
            return;
        }

        if (in_array($this->triggerType, $this->unimplementedTriggers, true)) {
            $this->addError('triggerType', 'This trigger is not available yet. Please pick another.');
            return;
        }

        $workspaceId = $this->workspaceId();

        // Enforce plan limit when creating a new workflow (not editing existing)
        if (! $this->workflowId) {
            try {
                $workspace = Workspace::findOrFail($workspaceId);
                app(PlanLimitService::class)->assertCanCreate($workspace, 'workflows');
            } catch (PlanLimitReachedException $e) {
                session()->flash('error', $e->getMessage());
                return;
            }
        }

        // Save the workflow (without redirect)
        if ($this->workflowId) {
            $workflow = Workflow::where('workspace_id', $workspaceId)
                ->findOrFail($this->workflowId);
            $workflow->update([
                'name' => $this->name,
                'description' => $this->description,
                'version' => $workflow->version + 1,
                'status' => 'active',
            ]);
        } else {
            $workflow = Workflow::create([
                'workspace_id' => $workspaceId,
                'created_by' => auth()->id(),
                'name' => $this->name,
                'description' => $this->description,
                'status' => 'active',
            ]);
            $this->workflowId = $workflow->id;
        }

        $this->persistNodesAndEdges($workflow);
        $this->ensureWebhookToken($workflow);

        session()->flash('success', 'Workflow saved and activated.');
        $this->redirect(route('workflows'), navigate: true);
    }

    // ──────────────────────────────────────────────────────────
    // Guided Mode Methods
    // ──────────────────────────────────────────────────────────

    /** Open the guided template picker overlay. */
    public function startGuidedMode(): void
    {
        $this->showGuidedMode = true;
        $this->guidedStep = 1;
        $this->selectedTemplate = null;
    }

    /** Select a template and advance to the customise step. */
    public function selectTemplate(string $templateId): void
    {
        $this->selectedTemplate = $templateId;
        $this->guidedStep = 2;
    }

    /**
     * Apply the selected template -- populates trigger, nodes, name,
     * and description from the template definition, then closes the overlay.
     */
    public function applyTemplate(): void
    {
        $template = collect($this->templates)->firstWhere('id', $this->selectedTemplate);
        if (!$template) {
            return;
        }

        $this->pushState();

        // Separate the trigger from action/condition nodes
        $templateNodes = $template['nodes'];
        $triggerDef = collect($templateNodes)->firstWhere('type', 'trigger');

        if ($triggerDef) {
            $this->triggerType = $triggerDef['subtype'];
            $this->triggerConfig = $triggerDef['config'] ?? [];
        }

        // Build action/condition node list (everything after trigger)
        $this->nodes = collect($templateNodes)
            ->where('type', '!=', 'trigger')
            ->values()
            ->map(fn(array $node) => [
                'id' => null,
                'type' => $node['type'],
                'subtype' => $node['subtype'],
                'config' => $this->getDefaultConfig($node['subtype']),
            ])
            ->toArray();

        $this->name = $template['name'];
        $this->description = $template['description'];
        $this->showGuidedMode = false;
        $this->selectedTemplate = null;
        $this->guidedStep = 1;
    }

    /** Close the guided mode overlay without applying anything. */
    public function exitGuidedMode(): void
    {
        $this->showGuidedMode = false;
        $this->selectedTemplate = null;
        $this->guidedStep = 1;
    }

    protected function workspaceId(): ?int
    {
        return auth()->user()->active_workspace_id;
    }

    /**
     * Apply a saved email template to a node's send_email config.
     * Loads the template's blocks, compiles them to HTML, and writes
     * subject/body_html/body into the node config so the workflow
     * email send can use the rendered template at runtime.
     *
     * Template id 0 / empty = "Custom" (clear template, leave subject/body editable).
     */
    public function applyEmailTemplate(int $nodeIndex, mixed $templateId): void
    {
        if (!isset($this->nodes[$nodeIndex]) || $this->nodes[$nodeIndex]['subtype'] !== 'send_email') {
            return;
        }

        $this->pushState();

        // Custom / no template: just record the choice, keep current subject/body editable
        if (empty($templateId)) {
            $this->nodes[$nodeIndex]['config']['email_template_id'] = '';
            return;
        }

        $template = EmailTemplate::forWorkspace($this->workspaceId())
            ->find((int) $templateId);

        if (!$template) {
            return;
        }

        $compiledHtml = EmailTemplateGallery::renderBlocksPreview($template->blocks ?? []);

        $this->nodes[$nodeIndex]['config']['email_template_id'] = (int) $template->id;
        $this->nodes[$nodeIndex]['config']['body_html'] = $compiledHtml;

        // Pre-fill subject from template name only if user hasn't typed their own
        if (empty($this->nodes[$nodeIndex]['config']['subject'] ?? '')) {
            $this->nodes[$nodeIndex]['config']['subject'] = $template->name;
        }

        // Mirror to plain `body` for backward compatibility with engine fallback
        $this->nodes[$nodeIndex]['config']['body'] = strip_tags($compiledHtml);

        $template->incrementUsage();
    }

    public function render()
    {
        $contactGroups = ContactList::where('workspace_id', $this->workspaceId())
            ->orderBy('name')
            ->get(['id', 'name']);

        // Email templates available for the send_email action dropdown:
        // workspace-owned templates first, then global defaults.
        // (Workflow builder + in-app notification channel are NOT gated —
        //  free for all plans.)
        $emailTemplates = EmailTemplate::forWorkspace($this->workspaceId())
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'category', 'is_default']);

        // Workflow features are always unlocked. Pass an "all true" map so
        // the blade doesn't need conditional null-safe handling.
        $planFeatures = [
            'email_templates' => true,
            'workflow_in_app_notify' => true,
        ];

        return view('livewire.workflows.workflow-builder', [
            'contactGroups'  => $contactGroups,
            'emailTemplates' => $emailTemplates,
            'planFeatures'   => $planFeatures,
        ]);
    }
}
