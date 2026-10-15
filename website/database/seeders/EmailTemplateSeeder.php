<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ---------------------------------------------------------------
            // 1. Welcome Email
            // ---------------------------------------------------------------
            [
                'name' => 'Welcome Email',
                'category' => 'onboarding',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Welcome aboard, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">We are thrilled to have you join us. Your account is ready and waiting for you to explore everything we have to offer.</p><p style="margin:0;color:#475569;">Here is what you can do to get started:</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">1. Complete your profile</p><p style="margin:0;font-size:14px;color:#64748B;">Add your details so your team can find and connect with you easily.</p>',
                            'right_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">2. Explore the dashboard</p><p style="margin:0;font-size:14px;color:#64748B;">Your dashboard gives you a clear view of all your activity at a glance.</p>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Go to Your Dashboard',
                            'url' => 'https://example.com/dashboard',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '80',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;">Need a hand getting started? Reply to this email or reach out to our support team at any time. We are here to help.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 2. Newsletter
            // ---------------------------------------------------------------
            [
                'name' => 'Newsletter',
                'category' => 'newsletter',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company} Newsletter',
                            'bg_color' => '#1E293B',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/6366F1/FFFFFF?text=Featured+Story',
                            'alt' => 'Featured story banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 12px 0;color:#1E293B;">This Month at {company}</h2><p style="margin:0 0 8px 0;color:#475569;">Hi {first_name}, here is a roundup of the latest news, tips, and updates from our team. We have been busy building new features and writing guides to help you succeed.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '100',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<h3 style="margin:0 0 8px 0;color:#6366F1;">Product Update</h3><p style="margin:0;font-size:14px;color:#64748B;">We launched a brand new dashboard with real-time analytics, custom widgets, and faster reporting tools.</p>',
                            'right_content' => '<h3 style="margin:0 0 8px 0;color:#6366F1;">Quick Tip</h3><p style="margin:0;font-size:14px;color:#64748B;">Did you know you can automate your weekly reports? Check out our new scheduling guide to save hours every week.</p>',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '100',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<h3 style="margin:0 0 8px 0;color:#6366F1;">From the Blog</h3><p style="margin:0;font-size:14px;color:#64748B;">Our latest post covers five strategies for improving your open rates by up to 40%. A must-read for marketers.</p>',
                            'right_content' => '<h3 style="margin:0 0 8px 0;color:#6366F1;">Community Spotlight</h3><p style="margin:0;font-size:14px;color:#64748B;">See how Acme Corp increased engagement by 3x using our segmentation tools. Read the full case study.</p>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Read More on Our Blog',
                            'url' => 'https://example.com/blog',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'social',
                        'data' => [
                            'links' => [
                                ['platform' => 'twitter', 'url' => 'https://twitter.com/example'],
                                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/example'],
                                ['platform' => 'facebook', 'url' => 'https://facebook.com/example'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe | Manage Preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 3. Product Announcement
            // ---------------------------------------------------------------
            [
                'name' => 'Product Announcement',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x300/6366F1/FFFFFF?text=Introducing+Our+Latest+Product',
                            'alt' => 'Product announcement hero image',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Introducing something we have been working on</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name},</p><p style="margin:0 0 12px 0;color:#475569;">We are excited to share our newest product with you. After months of development and testing with early users, it is finally ready for everyone.</p><p style="margin:0;color:#475569;">Here is what makes it special:</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Lightning fast</p><p style="margin:0;font-size:14px;color:#64748B;">Built from the ground up for performance. Load times are up to 10x faster than before.</p>',
                            'right_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Beautifully simple</p><p style="margin:0;font-size:14px;color:#64748B;">A redesigned interface that puts the most important actions right at your fingertips.</p>',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Powerful integrations</p><p style="margin:0;font-size:14px;color:#64748B;">Connects seamlessly with the tools you already use, from Slack to Salesforce.</p>',
                            'right_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Enterprise ready</p><p style="margin:0;font-size:14px;color:#64748B;">SOC 2 compliant with SSO, role-based access, and audit logging built in.</p>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'See It in Action',
                            'url' => 'https://example.com/product/new',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from product updates',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 4. Flash Sale
            // ---------------------------------------------------------------
            [
                'name' => 'Flash Sale',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#DC2626',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x280/DC2626/FFFFFF?text=FLASH+SALE',
                            'alt' => 'Flash sale banner',
                            'width' => '100',
                            'link_url' => 'https://example.com/sale',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 8px 0;color:#DC2626;text-align:center;">Limited Time: Save Big Today</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Hey {first_name}, we have an exclusive deal just for you. For the next 48 hours, enjoy significant savings across all plans. This is our biggest discount of the year.</p>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<div style="text-align:center;padding:16px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:13px;color:#991B1B;text-transform:uppercase;font-weight:600;">Starter Plan</p><p style="margin:0;font-size:28px;font-weight:700;color:#DC2626;"><s style="font-size:18px;color:#94A3B8;">$29</s> $14/mo</p></div>',
                            'right_content' => '<div style="text-align:center;padding:16px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:13px;color:#991B1B;text-transform:uppercase;font-weight:600;">Pro Plan</p><p style="margin:0;font-size:28px;font-weight:700;color:#DC2626;"><s style="font-size:18px;color:#94A3B8;">$79</s> $39/mo</p></div>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Claim Your Discount Now',
                            'url' => 'https://example.com/pricing?promo=FLASH',
                            'bg_color' => '#DC2626',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:13px;color:#94A3B8;text-align:center;">Offer ends in 48 hours. Cannot be combined with other promotions. Terms apply.</p>',
                            'align' => 'center',
                            'font_size' => '13',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from promotional emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 5. Abandoned Cart
            // ---------------------------------------------------------------
            [
                'name' => 'Abandoned Cart',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You left something behind</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name},</p><p style="margin:0 0 12px 0;color:#475569;">It looks like you started exploring our plans but did not finish. No worries, your selection is still saved and ready for you.</p><p style="margin:0;color:#475569;">If you had any questions or ran into an issue, we are happy to help. Just reply to this email.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '80',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F8FAFC;border-radius:8px;padding:0;"><tr><td style="padding:20px;"><p style="margin:0 0 4px 0;font-weight:700;color:#1E293B;">Your saved selection</p><p style="margin:0 0 4px 0;font-size:14px;color:#64748B;">Pro Plan &mdash; Monthly</p><p style="margin:0;font-size:20px;font-weight:700;color:#6366F1;">$79/month</p></td></tr></table>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Complete Your Purchase',
                            'url' => 'https://example.com/checkout/resume',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Your cart will be held for 7 days. After that, you can always start fresh.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 6. Thank You / Post-Purchase
            // ---------------------------------------------------------------
            [
                'name' => 'Thank You',
                'category' => 'transactional',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#059669',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#059669;text-align:center;">Thank you for your purchase!</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name},</p><p style="margin:0 0 12px 0;color:#475569;">Your order has been confirmed and is being processed. We truly appreciate your business and are committed to delivering the best possible experience.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '100',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F0FDF4;border-radius:8px;"><tr><td style="padding:20px;"><p style="margin:0 0 12px 0;font-weight:700;color:#1E293B;">Order Summary</p><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="padding:4px 0;font-size:14px;color:#475569;">Plan: Pro (Annual)</td><td style="padding:4px 0;font-size:14px;color:#475569;text-align:right;">$790.00</td></tr><tr><td style="padding:4px 0;font-size:14px;color:#475569;">Discount</td><td style="padding:4px 0;font-size:14px;color:#059669;text-align:right;">-$79.00</td></tr><tr><td style="padding:8px 0 0 0;font-size:16px;font-weight:700;color:#1E293B;border-top:1px solid #D1FAE5;">Total</td><td style="padding:8px 0 0 0;font-size:16px;font-weight:700;color:#1E293B;text-align:right;border-top:1px solid #D1FAE5;">$711.00</td></tr></table></td></tr></table>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'View Your Account',
                            'url' => 'https://example.com/account',
                            'bg_color' => '#059669',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Questions about your order? Contact us at support@example.com and we will be happy to help.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 7. Feedback Request / NPS Survey
            // ---------------------------------------------------------------
            [
                'name' => 'Feedback Request',
                'category' => 'engagement',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">How was your experience?</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name},</p><p style="margin:0 0 12px 0;color:#475569;">You have been using {company} for a while now, and your opinion matters a lot to us. We would love to hear how things are going and where we can improve.</p><p style="margin:0;color:#475569;">It takes less than 2 minutes and helps us build a better product for you.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '80',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 12px 0;color:#475569;text-align:center;font-weight:600;">On a scale of 0-10, how likely are you to recommend {company} to a colleague?</p><table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center" style="margin:0 auto;"><tr><td style="padding:4px;"><a href="https://example.com/nps?score=0" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FEF2F2;color:#DC2626;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">0</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=1" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FEF2F2;color:#DC2626;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">1</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=2" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FEF2F2;color:#DC2626;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">2</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=3" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FEF2F2;color:#DC2626;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">3</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=4" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FEF2F2;color:#DC2626;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">4</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=5" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FFF7ED;color:#EA580C;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">5</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=6" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#FFF7ED;color:#EA580C;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">6</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=7" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#ECFDF5;color:#059669;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">7</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=8" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#ECFDF5;color:#059669;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">8</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=9" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#ECFDF5;color:#059669;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">9</a></td><td style="padding:4px;"><a href="https://example.com/nps?score=10" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background:#ECFDF5;color:#059669;border-radius:6px;text-decoration:none;font-weight:600;font-size:13px;">10</a></td></tr></table><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top:4px;"><tr><td style="font-size:11px;color:#94A3B8;text-align:left;">Not likely</td><td style="font-size:11px;color:#94A3B8;text-align:right;">Very likely</td></tr></table>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Share Detailed Feedback',
                            'url' => 'https://example.com/feedback',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 8. Re-engagement / Win-back
            // ---------------------------------------------------------------
            [
                'name' => 'Re-engagement',
                'category' => 'engagement',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">We miss you, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">It has been a while since we last saw you, and a lot has changed. We have shipped some exciting updates that we think you will love.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="padding:12px 0;border-bottom:1px solid #F1F5F9;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding-right:12px;vertical-align:top;font-size:24px;">1</td><td><p style="margin:0 0 2px 0;font-weight:700;color:#1E293B;">Redesigned dashboard</p><p style="margin:0;font-size:14px;color:#64748B;">Cleaner layout, faster load times, and customizable widgets.</p></td></tr></table></td></tr><tr><td style="padding:12px 0;border-bottom:1px solid #F1F5F9;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding-right:12px;vertical-align:top;font-size:24px;">2</td><td><p style="margin:0 0 2px 0;font-weight:700;color:#1E293B;">Advanced automation</p><p style="margin:0;font-size:14px;color:#64748B;">Set up complex workflows in minutes with our visual builder.</p></td></tr></table></td></tr><tr><td style="padding:12px 0;"><table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr><td style="padding-right:12px;vertical-align:top;font-size:24px;">3</td><td><p style="margin:0 0 2px 0;font-weight:700;color:#1E293B;">Better analytics</p><p style="margin:0;font-size:14px;color:#64748B;">New engagement insights that show what is working and what is not.</p></td></tr></table></td></tr></table>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Come See What Is New',
                            'url' => 'https://example.com/dashboard',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Not interested anymore? No hard feelings. You can unsubscribe below and we will stop reaching out.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 9. Event Invitation
            // ---------------------------------------------------------------
            [
                'name' => 'Event Invitation',
                'category' => 'events',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#7C3AED',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x280/7C3AED/FFFFFF?text=You%27re+Invited',
                            'alt' => 'Event invitation banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You are invited, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">Join us for an exclusive live session where we will showcase our latest innovations, share actionable insights, and answer your questions in real time.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#7C3AED;font-size:13px;text-transform:uppercase;">Date & Time</p><p style="margin:0;font-size:14px;color:#475569;">April 10, 2026<br>2:00 PM - 4:00 PM EST</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#7C3AED;font-size:13px;text-transform:uppercase;">Location</p><p style="margin:0;font-size:14px;color:#475569;">Online Webinar<br>Link sent upon registration</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">What you will learn:</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; How top teams are using automation to scale faster</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; A live demo of our newest features</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Q&A with our product and engineering leads</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Reserve Your Spot',
                            'url' => 'https://example.com/events/register',
                            'bg_color' => '#7C3AED',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:13px;color:#94A3B8;text-align:center;">Spots are limited. Register now to guarantee your seat. A calendar invite and join link will be sent after registration.</p>',
                            'align' => 'center',
                            'font_size' => '13',
                        ],
                    ],
                    [
                        'type' => 'social',
                        'data' => [
                            'links' => [
                                ['platform' => 'twitter', 'url' => 'https://twitter.com/example'],
                                ['platform' => 'linkedin', 'url' => 'https://linkedin.com/company/example'],
                                ['platform' => 'instagram', 'url' => 'https://instagram.com/example'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe | View in browser',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 10. Monthly Report / Summary
            // ---------------------------------------------------------------
            [
                'name' => 'Monthly Report',
                'category' => 'reporting',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#1E293B',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 8px 0;color:#1E293B;text-align:center;">Your Monthly Summary</h2><p style="margin:0;color:#64748B;text-align:center;font-size:14px;">Here is how things went this past month, {first_name}.</p>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EEF2FF;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#6366F1;">12,847</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Emails Sent</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ECFDF5;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#059669;">42.3%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Open Rate</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFF7ED;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#EA580C;">8.7%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Click Rate</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FEF2F2;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#DC2626;">0.3%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Unsubscribe</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '100',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Top performing campaign</p><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F8FAFC;border-radius:8px;"><tr><td style="padding:16px;"><p style="margin:0 0 4px 0;font-weight:600;color:#1E293B;">Spring Product Launch</p><p style="margin:0;font-size:14px;color:#64748B;">52.1% open rate &middot; 14.3% click rate &middot; 2,341 recipients</p></td></tr></table>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'View Full Report',
                            'url' => 'https://example.com/reports/monthly',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe | Manage Preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 11. Feature Update / Changelog
            // ---------------------------------------------------------------
            [
                'name' => 'Feature Update',
                'category' => 'product',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 4px 0;font-size:13px;color:#6366F1;font-weight:600;text-transform:uppercase;">Product Update</p><h2 style="margin:0 0 12px 0;color:#1E293B;">What is new this month</h2><p style="margin:0;color:#475569;">Hi {first_name}, here is a quick look at the improvements and new features we shipped this month.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '100',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td style="padding:16px 0;border-bottom:1px solid #F1F5F9;"><p style="margin:0 0 2px 0;"><span style="display:inline-block;padding:2px 8px;background:#ECFDF5;color:#059669;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase;">New</span></p><p style="margin:6px 0 4px 0;font-weight:700;color:#1E293B;">Visual automation builder</p><p style="margin:0;font-size:14px;color:#64748B;">Create complex email workflows with our new drag-and-drop canvas. Set triggers, conditions, and actions without writing a single line of code.</p></td></tr><tr><td style="padding:16px 0;border-bottom:1px solid #F1F5F9;"><p style="margin:0 0 2px 0;"><span style="display:inline-block;padding:2px 8px;background:#EEF2FF;color:#6366F1;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase;">Improved</span></p><p style="margin:6px 0 4px 0;font-weight:700;color:#1E293B;">Campaign analytics</p><p style="margin:0;font-size:14px;color:#64748B;">Richer engagement data, heatmap click tracking, and device breakdown reports. See exactly how your audience interacts with every email.</p></td></tr><tr><td style="padding:16px 0;"><p style="margin:0 0 2px 0;"><span style="display:inline-block;padding:2px 8px;background:#FFF7ED;color:#EA580C;border-radius:4px;font-size:11px;font-weight:600;text-transform:uppercase;">Fixed</span></p><p style="margin:6px 0 4px 0;font-weight:700;color:#1E293B;">Timezone handling in scheduled sends</p><p style="margin:0;font-size:14px;color:#64748B;">Campaigns now respect the recipient timezone setting correctly, so your emails arrive at the intended local time.</p></td></tr></table>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Read the Full Changelog',
                            'url' => 'https://example.com/changelog',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Have a feature request? We would love to hear it. Reply to this email or visit our feedback board.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from product updates',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 12. Referral Program
            // ---------------------------------------------------------------
            [
                'name' => 'Referral Program',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Share the love, earn rewards</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name},</p><p style="margin:0;color:#475569;">Know someone who would benefit from {company}? Refer them and you both win. For every friend who signs up using your unique link, you will earn a credit toward your next bill.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => [
                            'color' => '#E5E7EB',
                            'width' => '80',
                            'style' => 'solid',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 16px 0;font-weight:700;color:#1E293B;text-align:center;">How it works</p><table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"><tr><td width="33%" style="text-align:center;vertical-align:top;padding:0 8px;"><div style="width:48px;height:48px;line-height:48px;border-radius:50%;background:#EEF2FF;color:#6366F1;font-weight:700;font-size:20px;display:inline-block;margin-bottom:8px;">1</div><p style="margin:0 0 4px 0;font-weight:600;color:#1E293B;font-size:14px;">Share your link</p><p style="margin:0;font-size:13px;color:#64748B;">Copy your unique referral link and send it to friends or colleagues.</p></td><td width="33%" style="text-align:center;vertical-align:top;padding:0 8px;"><div style="width:48px;height:48px;line-height:48px;border-radius:50%;background:#EEF2FF;color:#6366F1;font-weight:700;font-size:20px;display:inline-block;margin-bottom:8px;">2</div><p style="margin:0 0 4px 0;font-weight:600;color:#1E293B;font-size:14px;">They sign up</p><p style="margin:0;font-size:13px;color:#64748B;">Your friend creates an account using your link and starts their trial.</p></td><td width="33%" style="text-align:center;vertical-align:top;padding:0 8px;"><div style="width:48px;height:48px;line-height:48px;border-radius:50%;background:#EEF2FF;color:#6366F1;font-weight:700;font-size:20px;display:inline-block;margin-bottom:8px;">3</div><p style="margin:0 0 4px 0;font-weight:600;color:#1E293B;font-size:14px;">You both earn</p><p style="margin:0;font-size:13px;color:#64748B;">When they upgrade, you get a $25 credit and they get 20% off their first month.</p></td></tr></table>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EEF2FF;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0 0 8px 0;font-size:13px;color:#64748B;">Your referral link</p><p style="margin:0;font-size:16px;font-weight:600;color:#6366F1;word-break:break-all;">https://example.com/ref/{first_name}</p></td></tr></table>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Start Referring',
                            'url' => 'https://example.com/referrals',
                            'bg_color' => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:13px;color:#94A3B8;text-align:center;">There is no limit to the number of people you can refer. Credits are applied automatically to your next invoice.</p>',
                            'align' => 'center',
                            'font_size' => '13',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],
        ];

        foreach ($templates as $template) {
            EmailTemplate::updateOrCreate(
                ['name' => $template['name'], 'is_default' => true],
                [
                    'workspace_id' => null,
                    'blocks' => $template['blocks'],
                    'category' => $template['category'],
                    'is_default' => true,
                    'usage_count' => 0,
                ]
            );
        }
    }
}
