<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds 18 premium email templates across ecommerce, SaaS, agency, and real estate categories.
 *
 * Each template uses the block-based format consumed by the EmailBuilder component
 * and includes professional copy with personalization variables.
 */
class EmailTemplatePremiumSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ---------------------------------------------------------------
            // ECOMMERCE TEMPLATES (1-5)
            // ---------------------------------------------------------------

            // ---------------------------------------------------------------
            // 1. Cart Abandonment Reminder
            // ---------------------------------------------------------------
            [
                'name'     => 'Cart Abandonment Reminder',
                'category' => 'ecommerce',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#F59E0B',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You left something behind, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">It looks like you added some great items to your cart but did not complete your purchase. Do not worry, we saved everything for you.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x250/FEF3C7/92400E?text=Your+Cart+Items',
                            'alt'      => 'Cart items preview',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FFFBEB;border-radius:8px;padding:16px;border-left:4px solid #F59E0B;"><p style="margin:0 0 6px 0;font-weight:700;color:#92400E;">Items in your cart are selling fast</p><p style="margin:0;font-size:14px;color:#78716C;">Stock is limited and we cannot guarantee these items will be available much longer. Complete your order now to avoid missing out.</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:16px;background:#FEF3C7;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#92400E;text-transform:uppercase;font-weight:600;">Cart Total</p><p style="margin:0;font-size:22px;font-weight:700;color:#1E293B;">$149.99</p></div>',
                            'right_content' => '<div style="text-align:center;padding:16px;background:#FEF3C7;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#92400E;text-transform:uppercase;font-weight:600;">Items Saved</p><p style="margin:0;font-size:22px;font-weight:700;color:#1E293B;">3 Items</p></div>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Complete Your Purchase',
                            'url'        => 'https://example.com/cart',
                            'bg_color'   => '#F59E0B',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Need help checking out? Reply to this email or contact our support team. We are happy to assist.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from cart reminders',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 2. Shipping Confirmation
            // ---------------------------------------------------------------
            [
                'name'     => 'Shipping Confirmation',
                'category' => 'ecommerce',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#10B981',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#10B981;text-align:center;">Your order is on its way!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Great news, {first_name}! Your order has been shipped and is heading to your doorstep. Here are your shipping details.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#ECFDF5;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#059669;text-transform:uppercase;font-weight:600;">Tracking Number</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">1Z999AA10123456784</p></div>',
                            'right_content' => '<div style="padding:16px;background:#ECFDF5;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#059669;text-transform:uppercase;font-weight:600;">Estimated Delivery</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">March 28 - March 30</p></div>',
                        ],
                    ],
                    [
                        'type' => 'divider',
                        'data' => ['color' => '#E5E7EB', 'width' => '100', 'style' => 'solid'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:12px 0;font-weight:600;">Shipping Method</td><td style="padding:12px 0;text-align:right;">Express Delivery</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:12px 0;font-weight:600;">Ship To</td><td style="padding:12px 0;text-align:right;">{first_name}, Address on File</td></tr><tr><td style="padding:12px 0;font-weight:600;">Order Number</td><td style="padding:12px 0;text-align:right;font-weight:700;color:#1E293B;">#ORD-78452</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Track Your Package',
                            'url'        => 'https://example.com/tracking',
                            'bg_color'   => '#10B981',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Having issues with your delivery? Our support team is here to help 24/7. Simply reply to this email.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
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
            // 3. Product Review Request
            // ---------------------------------------------------------------
            [
                'name'     => 'Product Review Request',
                'category' => 'ecommerce',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#8B5CF6',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">How are you enjoying your purchase, {first_name}?</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Your order was delivered recently and we would love to hear what you think. Your review helps other shoppers make confident decisions and helps us improve.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x200/F5F3FF/5B21B6?text=Rate+Your+Purchase',
                            'alt'      => 'Rate your purchase banner',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="text-align:center;padding:20px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 8px 0;font-size:36px;">&#9733;&#9733;&#9733;&#9733;&#9733;</p><p style="margin:0;font-size:14px;color:#7C3AED;font-weight:600;">Tap the stars to leave your rating</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FAFAF9;border-radius:8px;padding:16px;border:1px solid #E7E5E4;"><p style="margin:0 0 8px 0;font-weight:700;color:#8B5CF6;">Leave a review and get rewarded</p><p style="margin:0;font-size:14px;color:#78716C;">As a thank you for sharing your honest feedback, you will receive a <strong>15% discount code</strong> for your next order. The code will be sent to your email after your review is submitted.</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Write a Review',
                            'url'        => 'https://example.com/review',
                            'bg_color'   => '#8B5CF6',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Not satisfied with your purchase? We are sorry to hear that. Reply to this email and our team will make it right.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from review requests',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 4. Back in Stock Alert
            // ---------------------------------------------------------------
            [
                'name'     => 'Back in Stock Alert',
                'category' => 'ecommerce',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#EF4444',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#EF4444;text-align:center;">It is back, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">The item you have been waiting for is back in stock. You asked us to notify you, and here we are. But act fast because inventory is limited.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x300/FEF2F2/991B1B?text=Product+Image',
                            'alt'      => 'Product back in stock',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FEF2F2;border-radius:8px;padding:16px;text-align:center;border:1px dashed #EF4444;"><p style="margin:0 0 6px 0;font-weight:700;color:#DC2626;font-size:18px;">Limited Stock Available</p><p style="margin:0;font-size:14px;color:#78716C;">This item sold out last time in under 48 hours. We recommend ordering soon to secure yours before it is gone again.</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:14px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#DC2626;text-transform:uppercase;font-weight:600;">Price</p><p style="margin:0;font-size:20px;font-weight:700;color:#1E293B;">$79.99</p></div>',
                            'right_content' => '<div style="text-align:center;padding:14px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#DC2626;text-transform:uppercase;font-weight:600;">Availability</p><p style="margin:0;font-size:20px;font-weight:700;color:#DC2626;">Low Stock</p></div>',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Buy Now Before It Sells Out',
                            'url'        => 'https://example.com/product',
                            'bg_color'   => '#EF4444',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">You received this email because you signed up for restock notifications. We will only notify you when this specific item is available.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from stock alerts',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 5. Loyalty Points Update
            // ---------------------------------------------------------------
            [
                'name'     => 'Loyalty Points Update',
                'category' => 'ecommerce',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#F97316',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Your Rewards Summary, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Here is your latest loyalty points update. You are making great progress towards your next reward!</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#FFF7ED;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#EA580C;text-transform:uppercase;font-weight:600;">Points Balance</p><p style="margin:0;font-size:28px;font-weight:700;color:#F97316;">2,450</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#FFF7ED;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#EA580C;text-transform:uppercase;font-weight:600;">Reward Tier</p><p style="margin:0;font-size:28px;font-weight:700;color:#F97316;">Gold</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FFF7ED;border-radius:8px;padding:16px;border-left:4px solid #F97316;"><p style="margin:0 0 8px 0;font-weight:700;color:#EA580C;">Rewards Available to Redeem</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10003; Free shipping on your next order (500 points)</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10003; $10 off any purchase (1,000 points)</p><p style="margin:0;font-size:14px;color:#78716C;">&#10003; Exclusive members-only sale access (2,000 points)</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#475569;text-align:center;">You are only <strong>550 points</strong> away from reaching Platinum tier. Keep shopping to unlock even more exclusive perks and higher reward rates.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Redeem Your Points',
                            'url'        => 'https://example.com/rewards',
                            'bg_color'   => '#F97316',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Earn 2x points this weekend on all purchases. Do not miss this limited-time bonus event!</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage rewards preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // SAAS TEMPLATES (6-10)
            // ---------------------------------------------------------------

            // ---------------------------------------------------------------
            // 6. Free Trial Ending
            // ---------------------------------------------------------------
            [
                'name'     => 'Free Trial Ending',
                'category' => 'saas',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#3B82F6',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Your free trial ends in 3 days, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">We hope you have been enjoying {company}. Your trial period is coming to an end, and we do not want you to lose access to the tools that have been helping your team succeed.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:16px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#3B82F6;text-transform:uppercase;font-weight:600;">Days Remaining</p><p style="margin:0;font-size:32px;font-weight:700;color:#DC2626;">3</p></div>',
                            'right_content' => '<div style="text-align:center;padding:16px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#3B82F6;text-transform:uppercase;font-weight:600;">Actions Taken</p><p style="margin:0;font-size:32px;font-weight:700;color:#3B82F6;">147</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0 0 12px 0;font-weight:700;color:#1E293B;">Here is what you will keep with a paid plan:</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Unlimited email campaigns and automations</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Advanced analytics and reporting dashboard</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Priority customer support with live chat</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Team collaboration with unlimited seats</p><p style="margin:0;font-size:14px;color:#475569;">&#10003; API access and custom integrations</p>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Upgrade Now',
                            'url'        => 'https://example.com/upgrade',
                            'bg_color'   => '#3B82F6',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Have questions about pricing or features? Schedule a quick call with our team and we will walk you through everything.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from trial reminders',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 7. Feature Announcement
            // ---------------------------------------------------------------
            [
                'name'     => 'Feature Announcement',
                'category' => 'saas',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#6366F1',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0 0 8px 0;font-size:12px;color:#6366F1;text-transform:uppercase;font-weight:600;text-align:center;letter-spacing:1px;">Product Update</p><h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Introducing Smart Automations, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">We have been working on something big, and it is finally here. Smart Automations lets you build powerful workflows without writing a single line of code.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x320/EEF2FF/4338CA?text=Smart+Automations+Screenshot',
                            'alt'      => 'Smart Automations feature screenshot',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;"><p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Drag-and-Drop Builder</p><p style="margin:0;font-size:14px;color:#64748B;">Create complex workflows visually with our intuitive drag-and-drop interface. No technical expertise required.</p></div>',
                            'right_content' => '<div style="padding:16px;"><p style="margin:0 0 6px 0;font-weight:700;color:#6366F1;">Pre-built Templates</p><p style="margin:0;font-size:14px;color:#64748B;">Get started instantly with 50+ automation templates for common tasks like onboarding, follow-ups, and reporting.</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#EEF2FF;border-radius:8px;padding:16px;text-align:center;"><p style="margin:0 0 6px 0;font-weight:700;color:#4338CA;">Available on all plans</p><p style="margin:0;font-size:14px;color:#64748B;">Smart Automations is now live in your dashboard. Log in to start building your first workflow today.</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Try Smart Automations',
                            'url'        => 'https://example.com/automations',
                            'bg_color'   => '#6366F1',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Want a walkthrough? Join our live demo webinar this Thursday at 2 PM EST.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage notification preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 8. Usage Report
            // ---------------------------------------------------------------
            [
                'name'     => 'Usage Report',
                'category' => 'saas',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#0EA5E9',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Your Monthly Usage Report</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Hi {first_name}, here is a summary of how your team used {company} this month. You have been making great progress!</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#F0F9FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#0284C7;text-transform:uppercase;font-weight:600;">Emails Sent</p><p style="margin:0;font-size:28px;font-weight:700;color:#0EA5E9;">12,847</p><p style="margin:4px 0 0 0;font-size:12px;color:#059669;">&#9650; 23% vs last month</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#F0F9FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#0284C7;text-transform:uppercase;font-weight:600;">Open Rate</p><p style="margin:0;font-size:28px;font-weight:700;color:#0EA5E9;">34.2%</p><p style="margin:4px 0 0 0;font-size:12px;color:#059669;">&#9650; 5% vs last month</p></div>',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x250/F0F9FF/0284C7?text=Performance+Chart',
                            'alt'      => 'Monthly performance chart',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Click-through Rate</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#0EA5E9;">8.7%</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Active Automations</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#0EA5E9;">12</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">New Subscribers</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#0EA5E9;">438</td></tr><tr><td style="padding:10px 0;font-weight:600;">Bounce Rate</td><td style="padding:10px 0;text-align:right;font-weight:700;color:#0EA5E9;">1.2%</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'View Full Analytics',
                            'url'        => 'https://example.com/analytics',
                            'bg_color'   => '#0EA5E9',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Pro tip: Emails sent on Tuesday and Thursday mornings see 18% higher open rates. Try adjusting your schedule to optimize performance.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage report preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 9. Subscription Downgrade Warning
            // ---------------------------------------------------------------
            [
                'name'     => 'Subscription Downgrade Warning',
                'category' => 'saas',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#DC2626',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Important: Your plan is about to change, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">We noticed you have scheduled a downgrade to the Basic plan. We want to make sure you understand what features you will lose access to so there are no surprises.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FEF2F2;border-radius:8px;padding:16px;border-left:4px solid #DC2626;"><p style="margin:0 0 10px 0;font-weight:700;color:#DC2626;">Features you will lose on the Basic plan:</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10007; Advanced automation workflows (currently using 8)</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10007; Custom reporting and analytics dashboard</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10007; Priority support with dedicated account manager</p><p style="margin:0 0 6px 0;font-size:14px;color:#78716C;">&#10007; Team collaboration (3 seats will be removed)</p><p style="margin:0;font-size:14px;color:#78716C;">&#10007; API access and third-party integrations</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:16px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#DC2626;text-transform:uppercase;font-weight:600;">Current Plan</p><p style="margin:0;font-size:18px;font-weight:700;color:#1E293B;">Professional</p><p style="margin:4px 0 0 0;font-size:14px;color:#64748B;">$49/month</p></div>',
                            'right_content' => '<div style="text-align:center;padding:16px;background:#FEF2F2;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#DC2626;text-transform:uppercase;font-weight:600;">Downgrading To</p><p style="margin:0;font-size:18px;font-weight:700;color:#1E293B;">Basic</p><p style="margin:4px 0 0 0;font-size:14px;color:#64748B;">$19/month</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#475569;text-align:center;">Your downgrade takes effect on <strong>April 1, 2026</strong>. You can cancel the downgrade anytime before that date to keep your current plan and features.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Keep My Professional Plan',
                            'url'        => 'https://example.com/billing',
                            'bg_color'   => '#DC2626',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">If cost is a concern, reply to this email. We may be able to offer a custom plan that fits your budget while keeping the features you use most.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage billing notifications',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 10. Team Invite
            // ---------------------------------------------------------------
            [
                'name'     => 'Team Invite',
                'category' => 'saas',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#8B5CF6',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You have been invited to join a team!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Hi {first_name}, a colleague has invited you to collaborate on {company}. Join their workspace and start working together today.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#7C3AED;text-transform:uppercase;font-weight:600;">Workspace</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">Marketing Team</p></div>',
                            'right_content' => '<div style="padding:16px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#7C3AED;text-transform:uppercase;font-weight:600;">Invited By</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">Alex Johnson</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#F5F3FF;border-radius:8px;padding:16px;"><p style="margin:0 0 10px 0;font-weight:700;color:#7C3AED;">What you will have access to:</p><p style="margin:0 0 6px 0;font-size:14px;color:#64748B;">&#10003; Shared email campaigns and templates</p><p style="margin:0 0 6px 0;font-size:14px;color:#64748B;">&#10003; Team analytics and performance dashboard</p><p style="margin:0 0 6px 0;font-size:14px;color:#64748B;">&#10003; Collaborative workflow builder</p><p style="margin:0;font-size:14px;color:#64748B;">&#10003; Real-time comments and feedback tools</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Accept Invitation',
                            'url'        => 'https://example.com/invite/accept',
                            'bg_color'   => '#8B5CF6',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">This invitation will expire in 7 days. If you did not expect this email, you can safely ignore it.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage invitation preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // AGENCY / PROFESSIONAL TEMPLATES (11-14)
            // ---------------------------------------------------------------

            // ---------------------------------------------------------------
            // 11. Project Status Update
            // ---------------------------------------------------------------
            [
                'name'     => 'Project Status Update',
                'category' => 'agency',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#0D9488',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Project Update: Milestone Reached!</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, we are excited to share that your project has reached a major milestone. Here is a summary of where things stand and what is coming next.</p>',
                            'align'     => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#F0FDFA;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#0D9488;text-transform:uppercase;font-weight:600;">Phase Completed</p><p style="margin:0;font-size:22px;font-weight:700;color:#1E293B;">Design</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#F0FDFA;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#0D9488;text-transform:uppercase;font-weight:600;">Overall Progress</p><p style="margin:0;font-size:22px;font-weight:700;color:#0D9488;">65%</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#F0FDFA;border-radius:8px;padding:16px;"><p style="margin:0 0 10px 0;font-weight:700;color:#0D9488;">Completed This Sprint</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Homepage and landing page designs finalized</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#10003; Brand style guide delivered and approved</p><p style="margin:0;font-size:14px;color:#475569;">&#10003; Mobile responsive mockups reviewed</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FAFAF9;border-radius:8px;padding:16px;border-left:4px solid #0D9488;"><p style="margin:0 0 10px 0;font-weight:700;color:#1E293B;">Next Steps</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">1. Begin front-end development (starting March 28)</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">2. Content integration and CMS setup</p><p style="margin:0;font-size:14px;color:#475569;">3. First development review session scheduled for April 4</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Schedule a Review Call',
                            'url'        => 'https://example.com/schedule',
                            'bg_color'   => '#0D9488',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Questions about the project? Reply to this email and your project manager will get back to you within 24 hours.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage project notifications',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 12. New Proposal Ready
            // ---------------------------------------------------------------
            [
                'name'     => 'New Proposal Ready',
                'category' => 'agency',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#4F46E5',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Your Proposal is Ready, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Thank you for the opportunity to work together. Based on our conversation, we have put together a customized proposal that outlines our recommended approach, timeline, and investment.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#EEF2FF;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#4F46E5;text-transform:uppercase;font-weight:600;">Project Scope</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">Website Redesign + Brand Strategy</p></div>',
                            'right_content' => '<div style="padding:16px;background:#EEF2FF;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#4F46E5;text-transform:uppercase;font-weight:600;">Estimated Timeline</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">8-10 Weeks</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FAFAF9;border-radius:8px;padding:16px;border:1px solid #E7E5E4;"><p style="margin:0 0 10px 0;font-weight:700;color:#4F46E5;">Proposal Highlights</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Discovery and research phase with stakeholder interviews</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Brand identity refresh with style guide deliverable</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Custom website design and responsive development</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Content strategy and SEO optimization</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; 30 days of post-launch support included</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#EEF2FF;border-radius:8px;padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#4F46E5;text-transform:uppercase;font-weight:600;">Investment</p><p style="margin:0;font-size:28px;font-weight:700;color:#1E293B;">$12,500 - $15,000</p><p style="margin:6px 0 0 0;font-size:13px;color:#64748B;">Flexible payment schedule available</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'View Full Proposal',
                            'url'        => 'https://example.com/proposal',
                            'bg_color'   => '#4F46E5',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">This proposal is valid for 30 days. Have questions? Let us schedule a quick call to walk through the details together.</p>',
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
            // 13. Client Onboarding Checklist
            // ---------------------------------------------------------------
            [
                'name'     => 'Client Onboarding Checklist',
                'category' => 'agency',
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
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Welcome aboard, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">We are thrilled to officially kick off our partnership. To ensure a smooth onboarding, here is a quick checklist of everything we need to get started. The sooner we complete these steps, the faster we can deliver results.</p>',
                            'align'     => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#ECFDF5;border-radius:8px;padding:20px;"><p style="margin:0 0 14px 0;font-weight:700;color:#059669;font-size:16px;">Your Onboarding Checklist</p><div style="margin:0 0 12px 0;padding:12px;background:#FFFFFF;border-radius:6px;border-left:4px solid #059669;"><p style="margin:0 0 2px 0;font-weight:600;color:#1E293B;">Step 1: Complete the intake questionnaire</p><p style="margin:0;font-size:13px;color:#64748B;">Share your goals, brand guidelines, and target audience details.</p></div><div style="margin:0 0 12px 0;padding:12px;background:#FFFFFF;border-radius:6px;border-left:4px solid #059669;"><p style="margin:0 0 2px 0;font-weight:600;color:#1E293B;">Step 2: Grant platform access</p><p style="margin:0;font-size:13px;color:#64748B;">Share login credentials or invite us to your analytics, CMS, and ad accounts.</p></div><div style="margin:0 0 12px 0;padding:12px;background:#FFFFFF;border-radius:6px;border-left:4px solid #059669;"><p style="margin:0 0 2px 0;font-weight:600;color:#1E293B;">Step 3: Upload brand assets</p><p style="margin:0;font-size:13px;color:#64748B;">Logos, fonts, color codes, and any existing style guides.</p></div><div style="margin:0 0 12px 0;padding:12px;background:#FFFFFF;border-radius:6px;border-left:4px solid #059669;"><p style="margin:0 0 2px 0;font-weight:600;color:#1E293B;">Step 4: Schedule the kickoff call</p><p style="margin:0;font-size:13px;color:#64748B;">Pick a time for our team to align on strategy and timeline.</p></div><div style="padding:12px;background:#FFFFFF;border-radius:6px;border-left:4px solid #059669;"><p style="margin:0 0 2px 0;font-weight:600;color:#1E293B;">Step 5: Review and sign the project agreement</p><p style="margin:0;font-size:13px;color:#64748B;">Finalize the scope of work and payment terms.</p></div></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#475569;">Your dedicated account manager will guide you through each step. We aim to complete onboarding within 5 business days so we can hit the ground running.</p>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Start Your Onboarding',
                            'url'        => 'https://example.com/onboarding',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Need help with any of these steps? Reply to this email or reach out to your account manager directly.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage communication preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 14. Invoice Reminder
            // ---------------------------------------------------------------
            [
                'name'     => 'Invoice Reminder',
                'category' => 'agency',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#EA580C',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Friendly Payment Reminder</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Hi {first_name}, we hope everything is going well. This is a friendly reminder that you have an outstanding invoice that is now past due. We would appreciate it if you could take a moment to process the payment.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#FFF7ED;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#EA580C;text-transform:uppercase;font-weight:600;">Invoice Number</p><p style="margin:0;font-size:18px;font-weight:700;color:#1E293B;">#INV-2026-0342</p></div>',
                            'right_content' => '<div style="padding:16px;background:#FFF7ED;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#EA580C;text-transform:uppercase;font-weight:600;">Due Date</p><p style="margin:0;font-size:18px;font-weight:700;color:#DC2626;">March 20, 2026</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#FFF7ED;border-radius:8px;padding:16px;text-align:center;border:1px solid #FDBA74;"><p style="margin:0 0 4px 0;font-size:12px;color:#EA580C;text-transform:uppercase;font-weight:600;">Amount Due</p><p style="margin:0;font-size:32px;font-weight:700;color:#1E293B;">$3,750.00</p><p style="margin:6px 0 0 0;font-size:13px;color:#DC2626;font-weight:600;">5 days past due</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Service</td><td style="padding:10px 0;text-align:right;">Website Development - Phase 2</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Billing Period</td><td style="padding:10px 0;text-align:right;">March 1 - March 15, 2026</td></tr><tr><td style="padding:10px 0;font-weight:600;">Payment Method</td><td style="padding:10px 0;text-align:right;">Bank Transfer or Credit Card</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Pay Now',
                            'url'        => 'https://example.com/invoice/pay',
                            'bg_color'   => '#EA580C',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">If you have already made this payment, please disregard this reminder. For any billing questions, reply to this email or contact our finance team.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage billing notifications',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // REAL ESTATE TEMPLATES (15-18)
            // ---------------------------------------------------------------

            // ---------------------------------------------------------------
            // 15. New Property Listing
            // ---------------------------------------------------------------
            [
                'name'     => 'New Property Listing',
                'category' => 'real_estate',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#0F766E',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">New Listing That Matches Your Search, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">We found a property that fits your criteria perfectly. Properties in this area are moving fast, so we wanted you to see it first.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x350/F0FDFA/134E4A?text=Property+Image',
                            'alt'      => 'Featured property listing',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="padding:16px;background:#F0FDFA;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#0F766E;text-transform:uppercase;font-weight:600;">Asking Price</p><p style="margin:0;font-size:22px;font-weight:700;color:#1E293B;">$485,000</p></div>',
                            'right_content' => '<div style="padding:16px;background:#F0FDFA;border-radius:8px;text-align:center;"><p style="margin:0 0 4px 0;font-size:12px;color:#0F766E;text-transform:uppercase;font-weight:600;">Property Type</p><p style="margin:0;font-size:22px;font-weight:700;color:#1E293B;">Single Family</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Bedrooms</td><td style="padding:10px 0;text-align:right;">4</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Bathrooms</td><td style="padding:10px 0;text-align:right;">2.5</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Square Feet</td><td style="padding:10px 0;text-align:right;">2,450 sq ft</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Year Built</td><td style="padding:10px 0;text-align:right;">2019</td></tr><tr><td style="padding:10px 0;font-weight:600;">Location</td><td style="padding:10px 0;text-align:right;">Oakwood Heights, Springfield</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#475569;">This beautifully maintained home features an open floor plan, modern kitchen with granite countertops, hardwood floors throughout, and a spacious backyard perfect for entertaining. Located in a top-rated school district with easy access to shopping and dining.</p>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Book a Viewing',
                            'url'        => 'https://example.com/listing/book',
                            'bg_color'   => '#0F766E',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Want to see more properties? Reply to this email and your agent will send a personalized selection.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from listing alerts',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 16. Open House Invitation
            // ---------------------------------------------------------------
            [
                'name'     => 'Open House Invitation',
                'category' => 'real_estate',
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
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You Are Invited to an Open House, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">We are hosting an open house this weekend at one of our featured properties. Come see this stunning home in person and explore the neighborhood.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x300/F5F3FF/5B21B6?text=Open+House+Property',
                            'alt'      => 'Open house property image',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:16px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#7C3AED;text-transform:uppercase;font-weight:600;">Date</p><p style="margin:0;font-size:18px;font-weight:700;color:#1E293B;">Saturday, April 5</p></div>',
                            'right_content' => '<div style="text-align:center;padding:16px;background:#F5F3FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#7C3AED;text-transform:uppercase;font-weight:600;">Time</p><p style="margin:0;font-size:18px;font-weight:700;color:#1E293B;">1:00 PM - 4:00 PM</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#F5F3FF;border-radius:8px;padding:16px;text-align:center;border:1px solid #DDD6FE;"><p style="margin:0 0 4px 0;font-size:12px;color:#7C3AED;text-transform:uppercase;font-weight:600;">Address</p><p style="margin:0;font-size:16px;font-weight:700;color:#1E293B;">742 Evergreen Terrace, Springfield, IL 62704</p><p style="margin:8px 0 0 0;font-size:13px;color:#64748B;">Free street parking available. Look for the {company} signs.</p></div>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0 0 12px 0;font-weight:700;color:#1E293B;">Property Highlights:</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; 4 bedrooms, 3 bathrooms, 2,800 sq ft</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Recently renovated kitchen and master suite</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">&#8226; Private backyard with covered patio and pool</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Listed at $539,000</p>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'RSVP for Open House',
                            'url'        => 'https://example.com/open-house/rsvp',
                            'bg_color'   => '#7C3AED',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Cannot make it? Reply to this email to schedule a private showing at a time that works for you.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from event invitations',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 17. Market Update Report
            // ---------------------------------------------------------------
            [
                'name'     => 'Market Update Report',
                'category' => 'real_estate',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#1D4ED8',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Your Monthly Market Update</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Hi {first_name}, here is your personalized real estate market report for March 2026. Stay informed with the latest trends in your area.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#1D4ED8;text-transform:uppercase;font-weight:600;">Median Home Price</p><p style="margin:0;font-size:24px;font-weight:700;color:#1E293B;">$425,000</p><p style="margin:4px 0 0 0;font-size:12px;color:#059669;">&#9650; 4.2% year over year</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#EFF6FF;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#1D4ED8;text-transform:uppercase;font-weight:600;">Days on Market</p><p style="margin:0;font-size:24px;font-weight:700;color:#1E293B;">28</p><p style="margin:4px 0 0 0;font-size:12px;color:#DC2626;">&#9660; 6 days vs last month</p></div>',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src'      => 'https://placehold.co/600x250/EFF6FF/1E40AF?text=Price+Trend+Chart',
                            'alt'      => 'Market price trend chart',
                            'width'    => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Active Listings</td><td style="padding:10px 0;text-align:right;font-weight:700;">342</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Homes Sold This Month</td><td style="padding:10px 0;text-align:right;font-weight:700;">89</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Average Sale to List Ratio</td><td style="padding:10px 0;text-align:right;font-weight:700;">98.5%</td></tr><tr><td style="padding:10px 0;font-weight:600;">New Listings This Month</td><td style="padding:10px 0;text-align:right;font-weight:700;">127</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#EFF6FF;border-radius:8px;padding:16px;border-left:4px solid #1D4ED8;"><p style="margin:0 0 8px 0;font-weight:700;color:#1D4ED8;">Market Insight</p><p style="margin:0;font-size:14px;color:#475569;">The local market continues to favor sellers with inventory remaining below the 3-month supply threshold. However, new construction in the area is expected to bring more options for buyers in Q2. Now is a strategic time to evaluate your position whether buying or selling.</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Schedule a Consultation',
                            'url'        => 'https://example.com/consultation',
                            'bg_color'   => '#1D4ED8',
                            'text_color' => '#FFFFFF',
                            'align'      => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Want a free home valuation? Reply to this email and we will prepare a detailed analysis for your property.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from market reports',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 18. Mortgage Pre-Approval
            // ---------------------------------------------------------------
            [
                'name'     => 'Mortgage Pre-Approval',
                'category' => 'real_estate',
                'blocks'   => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url'     => '',
                            'company_name' => '{company}',
                            'bg_color'     => '#15803D',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<h2 style="margin:0 0 16px 0;color:#15803D;text-align:center;">Congratulations, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;text-align:center;">Great news! You have been pre-approved for a mortgage. This is an exciting first step on your journey to homeownership. Here is a summary of your pre-approval details.</p>',
                            'align'     => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content'  => '<div style="text-align:center;padding:20px;background:#F0FDF4;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#15803D;text-transform:uppercase;font-weight:600;">Pre-Approved Amount</p><p style="margin:0;font-size:26px;font-weight:700;color:#1E293B;">$450,000</p></div>',
                            'right_content' => '<div style="text-align:center;padding:20px;background:#F0FDF4;border-radius:8px;"><p style="margin:0 0 4px 0;font-size:12px;color:#15803D;text-transform:uppercase;font-weight:600;">Interest Rate</p><p style="margin:0;font-size:26px;font-weight:700;color:#1E293B;">5.75%</p></div>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<table width="100%" style="font-size:14px;color:#475569;"><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Loan Type</td><td style="padding:10px 0;text-align:right;">30-Year Fixed</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Estimated Monthly Payment</td><td style="padding:10px 0;text-align:right;font-weight:700;">$2,627</td></tr><tr style="border-bottom:1px solid #E5E7EB;"><td style="padding:10px 0;font-weight:600;">Pre-Approval Valid Until</td><td style="padding:10px 0;text-align:right;">June 25, 2026</td></tr><tr><td style="padding:10px 0;font-weight:600;">Reference Number</td><td style="padding:10px 0;text-align:right;">PA-2026-08741</td></tr></table>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content'   => '<div style="background:#F0FDF4;border-radius:8px;padding:16px;border-left:4px solid #15803D;"><p style="margin:0 0 10px 0;font-weight:700;color:#15803D;">Your Next Steps</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">1. Connect with your real estate agent to begin your home search</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">2. Download your pre-approval letter for property viewings</p><p style="margin:0 0 6px 0;font-size:14px;color:#475569;">3. Gather documentation for final mortgage application</p><p style="margin:0;font-size:14px;color:#475569;">4. Schedule a consultation to discuss your home buying strategy</p></div>',
                            'align'     => 'left',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text'       => 'Contact Your Agent',
                            'url'        => 'https://example.com/agent/contact',
                            'bg_color'   => '#15803D',
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
                            'content'   => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">This pre-approval is subject to final verification and property appraisal. Terms and conditions apply. Contact us with any questions about your mortgage options.</p>',
                            'align'     => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text'             => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Manage communication preferences',
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
