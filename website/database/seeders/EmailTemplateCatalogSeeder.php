<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds additional professional email templates to complement the base EmailTemplateSeeder.
 *
 * These bring the catalog to 16 total templates covering every major email campaign category.
 * Each template uses the block-based format consumed by the EmailBuilder component.
 */
class EmailTemplateCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ---------------------------------------------------------------
            // 1. Follow-up After Meeting
            // ---------------------------------------------------------------
            [
                'name'     => 'Follow-up After Meeting',
                'category' => 'engagement',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#059669',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Great meeting you, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">Thank you for taking the time to chat with us today. We really enjoyed learning more about your goals and how we might be able to help.</p><p style="margin:0 0 12px 0;color:#475569;">Here is a quick recap of what we discussed:</p>',
                            'align'     => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<p style="margin:0 0 6px 0;font-weight:700;color:#059669;">Key Topics</p><p style="margin:0;font-size:14px;color:#64748B;">We covered your current workflow, pain points, and areas where automation could save your team significant time.</p>',
                            'right_content' => '<p style="margin:0 0 6px 0;font-weight:700;color:#059669;">Next Steps</p><p style="margin:0;font-size:14px;color:#64748B;">We will prepare a customized proposal based on our conversation and send it over within the next 2 business days.</p>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Schedule a Follow-up',
                            'url'        => 'https://example.com/schedule',
                            'bg_color'   => '#059669',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => ['color' => '#E5E7EB', 'width' => '80', 'style' => 'solid'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;">If you have any questions in the meantime, simply reply to this email. We are always happy to help.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 2. Order Confirmation
            // ---------------------------------------------------------------
            [
                'name'     => 'Order Confirmation',
                'category' => 'transactional',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#2563EB',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 8px 0;color:#2563EB;text-align:center;">Order Confirmed!</h2><p style="margin:0 0 16px 0;color:#475569;text-align:center;">Hi {first_name}, thank you for your purchase. We are processing your order now.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#3B82F6;text-transform:uppercase;font-weight:600;">Order Number</p><p style="margin:0;font-size:20px;font-weight:700;color:#1E293B;">#MB-12345</p></div>',
                            'right_content' => '<div style="padding:16px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#3B82F6;text-transform:uppercase;font-weight:600;">Estimated Delivery</p><p style="margin:0;font-size:20px;font-weight:700;color:#1E293B;">3-5 Days</p></div>',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => ['color' => '#E5E7EB', 'width' => '100', 'style' => 'solid'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:12px 0;font-weight:600;">Item</td><td style="padding:12px 0;text-align:right;font-weight:600;">Price</td></tr><tr><td style="padding:10px 0;">Pro Plan - Annual Subscription</td><td style="padding:10px 0;text-align:right;">$599.00</td></tr><tr style="border-top:1px solid #E5E7EB;"><td style="padding:12px 0;font-weight:700;color:#1E293B;">Total</td><td style="padding:12px 0;text-align:right;font-weight:700;color:#1E293B;">$599.00</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'View Order Details',
                            'url'        => 'https://example.com/orders',
                            'bg_color'   => '#2563EB',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage email preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 3. Holiday Greeting
            // ---------------------------------------------------------------
            [
                'name'     => 'Holiday Greeting',
                'category' => 'engagement',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#B91C1C',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x280/B91C1C/FFFFFF?text=Happy+Holidays',
                            'alt'      => 'Happy Holidays banner',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#B91C1C;text-align:center;">Season\'s Greetings, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">As this year comes to a close, we want to take a moment to express our heartfelt gratitude. Your trust and partnership have been invaluable, and we could not have done it without you.</p><p style="margin:0;color:#475569;text-align:center;">From our entire team, we wish you a wonderful holiday season filled with joy, warmth, and prosperity.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'See Our Year in Review',
                            'url'        => 'https://example.com/year-in-review',
                            'bg_color'   => '#B91C1C',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Wishing you all the best in the new year!</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from these emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 4. Survey / Feedback Request (Extended)
            // ---------------------------------------------------------------
            [
                'name'     => 'Customer Satisfaction Survey',
                'category' => 'engagement',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#7C3AED',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">We would love your feedback, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Your opinion matters to us. Please take 2 minutes to share your experience and help us improve our service.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 8px 0;font-size:32px;">2</p><p style="margin:0;font-size:13px;color:#7C3AED;font-weight:600;">Minutes to complete</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 8px 0;font-size:32px;">5</p><p style="margin:0;font-size:13px;color:#7C3AED;font-weight:600;">Quick questions</p></div>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Start Survey',
                            'url'        => 'https://example.com/survey',
                            'bg_color'   => '#7C3AED',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => ['color' => '#E5E7EB', 'width' => '80', 'style' => 'solid'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">As a thank you, everyone who completes the survey will receive a 10% discount on their next purchase.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from survey emails',
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
                    'category'     => $template['category'],
                    'blocks'       => $template['blocks'],
                    'is_default'   => true,
                    'usage_count'  => 0,
                ],
            );
        }
    }
}
