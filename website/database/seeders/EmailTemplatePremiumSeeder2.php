<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplatePremiumSeeder2 extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ---------------------------------------------------------------
            // 1. Appointment Confirmation (Healthcare)
            // ---------------------------------------------------------------
            [
                'name' => 'Appointment Confirmation',
                'category' => 'healthcare',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#0891B2',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Your Appointment is Confirmed</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, your upcoming appointment has been scheduled and confirmed. Please review the details below and save this email for your records.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ECFEFF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#0891B2;font-size:13px;text-transform:uppercase;">Date &amp; Time</p><p style="margin:0;font-size:14px;color:#475569;">Thursday, April 15, 2026<br>10:30 AM - 11:15 AM</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ECFEFF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#0891B2;font-size:13px;text-transform:uppercase;">Provider</p><p style="margin:0;font-size:14px;color:#475569;">Dr. Sarah Mitchell<br>Internal Medicine</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Preparation Instructions</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Please arrive 15 minutes early to complete any necessary paperwork.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Bring a valid photo ID and your insurance card.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; If you take daily medications, please bring a current list.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Fast for 8 hours if lab work has been requested.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Manage Your Booking',
                            'url' => 'https://example.com/appointments/manage',
                            'bg_color' => '#0891B2',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Need to reschedule or cancel? Please contact us at least 24 hours in advance by calling (555) 123-4567 or using the link above.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 456 Healthcare Blvd, Suite 200',
                            'unsubscribe_text' => 'Unsubscribe from appointment reminders',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 2. Wellness Check-In (Healthcare)
            // ---------------------------------------------------------------
            [
                'name' => 'Wellness Check-In',
                'category' => 'healthcare',
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
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/059669/FFFFFF?text=Your+Wellness+Matters',
                            'alt' => 'Wellness check-in banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Hi {first_name}, How Are You Feeling?</h2><p style="margin:0 0 12px 0;color:#475569;">It has been a while since your last visit, and we wanted to check in. Regular wellness checkups are one of the most effective ways to stay ahead of potential health concerns and maintain your quality of life.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<h3 style="margin:0 0 8px 0;color:#059669;">Seasonal Wellness Tips</h3><p style="margin:0;font-size:14px;color:#64748B;">Stay hydrated, get at least 7 hours of sleep, and aim for 30 minutes of moderate exercise daily. Small habits make a big difference over time.</p>',
                            'right_content' => '<h3 style="margin:0 0 8px 0;color:#059669;">Preventive Screenings</h3><p style="margin:0;font-size:14px;color:#64748B;">Based on your age and health profile, you may be due for routine bloodwork, vision, or dental screenings. Ask us during your next visit.</p>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;color:#475569;">We have availability this month and would love to see you. Booking takes less than a minute, and your health is worth the investment.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Book Your Checkup',
                            'url' => 'https://example.com/book-checkup',
                            'bg_color' => '#059669',
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
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 456 Healthcare Blvd, Suite 200',
                            'unsubscribe_text' => 'Unsubscribe from wellness emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 3. Fitness Class Reminder
            // ---------------------------------------------------------------
            [
                'name' => 'Fitness Class Reminder',
                'category' => 'fitness',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#E11D48',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Your Class Starts in 24 Hours!</h2><p style="margin:0 0 12px 0;color:#475569;">Hey {first_name}, just a friendly reminder that you are booked into an upcoming fitness class. We can not wait to see you there!</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFF1F2;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#E11D48;font-size:13px;text-transform:uppercase;">Class</p><p style="margin:0;font-size:14px;color:#475569;">HIIT Power Hour<br>with Coach Alex Rivera</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFF1F2;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#E11D48;font-size:13px;text-transform:uppercase;">When &amp; Where</p><p style="margin:0;font-size:14px;color:#475569;">Tomorrow, 7:00 AM<br>Studio B, Main Floor</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">What to Bring</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Water bottle and sweat towel</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Comfortable workout shoes and athletic wear</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Positive energy and a ready-to-go attitude</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Cancel or Reschedule',
                            'url' => 'https://example.com/classes/manage',
                            'bg_color' => '#E11D48',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Please cancel at least 4 hours before class time to avoid a late cancellation fee. See you on the mat!</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 789 Fitness Ave, Ground Floor',
                            'unsubscribe_text' => 'Unsubscribe from class reminders',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 4. Course Enrollment Confirmation (Education)
            // ---------------------------------------------------------------
            [
                'name' => 'Course Enrollment Confirmation',
                'category' => 'education',
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
                            'src' => 'https://placehold.co/600x250/7C3AED/FFFFFF?text=Welcome+to+Your+Course',
                            'alt' => 'Course enrollment banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">You Are Enrolled, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">Congratulations on taking the next step in your learning journey. Your enrollment has been confirmed, and we are excited to have you in the class.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#7C3AED;font-size:13px;text-transform:uppercase;">Course</p><p style="margin:0;font-size:14px;color:#475569;">Advanced Data Analytics<br>12-week program</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#7C3AED;font-size:13px;text-transform:uppercase;">Start Date</p><p style="margin:0;font-size:14px;color:#475569;">May 5, 2026<br>Live sessions: Mon &amp; Wed</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Before You Begin</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Course materials and syllabus are available in your student portal.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Join the student community forum to meet your classmates.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Download the companion app for offline access to lectures and notes.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Access Your Course',
                            'url' => 'https://example.com/courses/dashboard',
                            'bg_color' => '#7C3AED',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Questions about the course? Reach out to your instructor or our student support team at support@example.com.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 321 Education Lane, Building A',
                            'unsubscribe_text' => 'Unsubscribe from course emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 5. Assignment Reminder (Education)
            // ---------------------------------------------------------------
            [
                'name' => 'Assignment Reminder',
                'category' => 'education',
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
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Assignment Due Soon, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">This is a friendly reminder that your assignment is due in 48 hours. Make sure to review the requirements and submit your work before the deadline.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FEF2F2;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#DC2626;font-size:13px;text-transform:uppercase;">Assignment</p><p style="margin:0;font-size:14px;color:#475569;">Module 4: Case Study Analysis<br>Weight: 20% of final grade</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FEF2F2;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#DC2626;font-size:13px;text-transform:uppercase;">Deadline</p><p style="margin:0;font-size:14px;color:#475569;">Friday, April 18, 2026<br>11:59 PM EST</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Tips for Success</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Review the grading rubric in your course materials before starting.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Cite all sources using the required format (APA/MLA as noted).</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Proofread your submission carefully before uploading.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Submit early to avoid last-minute technical issues.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Submit Your Assignment',
                            'url' => 'https://example.com/assignments/submit',
                            'bg_color' => '#DC2626',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Late submissions may incur a penalty. If you need an extension, contact your instructor before the deadline.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 321 Education Lane, Building A',
                            'unsubscribe_text' => 'Unsubscribe from assignment reminders',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 6. Certificate of Completion (Education)
            // ---------------------------------------------------------------
            [
                'name' => 'Certificate of Completion',
                'category' => 'education',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#16A34A',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/16A34A/FFFFFF?text=Congratulations!',
                            'alt' => 'Certificate of completion banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Congratulations, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">You have successfully completed <strong>Advanced Data Analytics</strong>. Your dedication and hard work have paid off, and we could not be more proud of your achievement.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ECFDF5;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#16A34A;">12</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Weeks Completed</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#ECFDF5;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#16A34A;">94%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Final Score</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 12px 0;color:#475569;">Your certificate is ready to download. Share it on LinkedIn, add it to your resume, or print it for your records. This accomplishment is worth celebrating.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Download Your Certificate',
                            'url' => 'https://example.com/certificates/download',
                            'bg_color' => '#16A34A',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Ready for your next challenge? Browse our course catalog to continue building your skills with {company}.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 321 Education Lane, Building A',
                            'unsubscribe_text' => 'Unsubscribe from course emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 7. Reservation Confirmation (Hospitality)
            // ---------------------------------------------------------------
            [
                'name' => 'Reservation Confirmation',
                'category' => 'hospitality',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#B45309',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Your Table is Reserved, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">Thank you for choosing {company}. We have reserved your table and look forward to welcoming you for an exceptional dining experience.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFFBEB;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#B45309;font-size:13px;text-transform:uppercase;">Date &amp; Time</p><p style="margin:0;font-size:14px;color:#475569;">Saturday, April 19, 2026<br>7:30 PM</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFFBEB;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#B45309;font-size:13px;text-transform:uppercase;">Party Size</p><p style="margin:0;font-size:14px;color:#475569;">4 Guests<br>Indoor Seating</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Good to Know</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; We hold reservations for 15 minutes past the booking time.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Please let us know about any dietary requirements or allergies in advance.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Valet parking is available at the main entrance.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Modify Reservation',
                            'url' => 'https://example.com/reservations/modify',
                            'bg_color' => '#B45309',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Need to cancel? Please do so at least 2 hours before your reservation time. Call us at (555) 987-6543.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 55 Gourmet Street, Downtown',
                            'unsubscribe_text' => 'Unsubscribe from reservation emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 8. Special Menu Preview (Hospitality)
            // ---------------------------------------------------------------
            [
                'name' => 'Special Menu Preview',
                'category' => 'hospitality',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#9333EA',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x280/9333EA/FFFFFF?text=New+Seasonal+Menu',
                            'alt' => 'Seasonal menu preview banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">A New Season, A New Menu</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, our chefs have been hard at work crafting a seasonal menu that celebrates the freshest ingredients of the season. Here is a sneak peek at what awaits you.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<h3 style="margin:0 0 8px 0;color:#9333EA;">Spring Pea Risotto</h3><p style="margin:0;font-size:14px;color:#64748B;">Arborio rice slow-cooked with fresh spring peas, shaved parmesan, and a hint of lemon zest. Light, creamy, and perfectly seasonal.</p>',
                            'right_content' => '<h3 style="margin:0 0 8px 0;color:#9333EA;">Herb-Crusted Lamb</h3><p style="margin:0;font-size:14px;color:#64748B;">Tender lamb rack with a rosemary and thyme crust, served alongside roasted root vegetables and a red wine reduction.</p>',
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
                            'content' => '<p style="margin:0;color:#475569;text-align:center;"><strong>Featured Dessert:</strong> Lavender Cr&egrave;me Br&ucirc;l&eacute;e &mdash; a delicate custard infused with Proven&ccedil;al lavender, finished with a crisp caramelized sugar top. Tables are filling up fast for opening week.</p>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Reserve Your Table',
                            'url' => 'https://example.com/reserve',
                            'bg_color' => '#9333EA',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 55 Gourmet Street, Downtown',
                            'unsubscribe_text' => 'Unsubscribe | View full menu online',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 9. Loyalty Reward Earned (Hospitality)
            // ---------------------------------------------------------------
            [
                'name' => 'Loyalty Reward Earned',
                'category' => 'hospitality',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#C2410C',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">You Have Earned a Reward!</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, your loyalty means the world to us. As a thank you for being a valued guest at {company}, you have unlocked a special reward.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFF7ED;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#C2410C;">FREE</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Complimentary Dessert</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFF7ED;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#C2410C;">15%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Off Your Next Visit</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;color:#475569;">Simply show this email or use the code below when you visit. This reward is valid for dine-in only and cannot be combined with other offers.</p><p style="margin:0;font-size:14px;font-weight:700;color:#C2410C;text-align:center;">Reward Code: LOYAL2026 &middot; Expires: May 31, 2026</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Redeem Your Reward',
                            'url' => 'https://example.com/rewards/redeem',
                            'bg_color' => '#C2410C',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Keep earning points with every visit. You are just 3 visits away from your next reward tier!</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 55 Gourmet Street, Downtown',
                            'unsubscribe_text' => 'Unsubscribe from loyalty emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 10. Donation Thank You (Nonprofit)
            // ---------------------------------------------------------------
            [
                'name' => 'Donation Thank You',
                'category' => 'nonprofit',
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
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/059669/FFFFFF?text=Thank+You+for+Your+Generosity',
                            'alt' => 'Donation thank you banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">Thank You, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">Your generous donation to {company} makes a real difference. Every contribution, no matter the size, helps us continue our mission and create lasting change in the communities we serve.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Your Impact at a Glance</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; 500+ families received essential supplies this quarter.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; 120 students gained access to educational programs.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; 3 new community centers were funded and opened.</p>',
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
                            'content' => '<p style="margin:0;color:#475569;"><strong>Tax Receipt:</strong> A detailed tax-deductible receipt has been sent to your email on file. Please retain it for your records. If you need a duplicate, contact us at donations@example.com.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Share Our Mission',
                            'url' => 'https://example.com/share',
                            'bg_color' => '#059669',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Help us amplify our impact by sharing our story with friends and family. Together, we can do even more.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 100 Charity Way, Nonprofit District',
                            'unsubscribe_text' => 'Unsubscribe from donation updates',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 11. Volunteer Opportunity (Nonprofit)
            // ---------------------------------------------------------------
            [
                'name' => 'Volunteer Opportunity',
                'category' => 'nonprofit',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#2563EB',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/2563EB/FFFFFF?text=Make+a+Difference',
                            'alt' => 'Volunteer opportunity banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">We Need Your Help, {first_name}</h2><p style="margin:0 0 12px 0;color:#475569;">{company} is organizing a community outreach event and we are looking for passionate volunteers like you. Your time and skills can make a tremendous difference in someone\'s life.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EFF6FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#2563EB;font-size:13px;text-transform:uppercase;">Event</p><p style="margin:0;font-size:14px;color:#475569;">Community Clean-Up Day<br>Saturday, May 10, 2026</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EFF6FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#2563EB;font-size:13px;text-transform:uppercase;">Time &amp; Location</p><p style="margin:0;font-size:14px;color:#475569;">9:00 AM - 1:00 PM<br>Central Community Park</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Roles Available</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; <strong>Team Leaders:</strong> Guide groups of 5-8 volunteers through assigned zones.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; <strong>Setup &amp; Logistics:</strong> Help with equipment distribution and site preparation.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; <strong>General Volunteers:</strong> Participate in cleanup activities and community engagement.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Sign Up to Volunteer',
                            'url' => 'https://example.com/volunteer/signup',
                            'bg_color' => '#2563EB',
                            'text_color' => '#FFFFFF',
                            'align' => 'center',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Last year, 200 volunteers helped us restore 15 acres of green space. Let us make an even bigger impact this year.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 100 Charity Way, Nonprofit District',
                            'unsubscribe_text' => 'Unsubscribe from volunteer opportunities',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 12. Company Anniversary (Engagement)
            // ---------------------------------------------------------------
            [
                'name' => 'Company Anniversary',
                'category' => 'engagement',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#D97706',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x280/D97706/FFFFFF?text=Celebrating+10+Years',
                            'alt' => 'Company anniversary celebration banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">10 Years of {company}!</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, we are celebrating a decade of innovation, growth, and incredible customers like you. None of this would have been possible without your trust and support.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFFBEB;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#D97706;">50K+</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Customers Served</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FFFBEB;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#D97706;">30+</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Countries Reached</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;color:#475569;">To say thank you, we are offering an exclusive anniversary discount. Use the code below at checkout for 25% off any plan or product through the end of the month.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Celebrate With Us — Use Code ANNIVERSARY25',
                            'url' => 'https://example.com/anniversary',
                            'bg_color' => '#D97706',
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
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe | Manage Preferences',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 13. Referral Program (Marketing)
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
                            'bg_color' => '#7C3AED',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Give $20, Get $20</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, love using {company}? Share it with your friends and you will both be rewarded. For every friend who signs up using your unique link, you each receive a $20 credit.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 6px 0;font-weight:700;color:#7C3AED;">Step 1</p><p style="margin:0;font-size:14px;color:#64748B;">Share your unique referral link with friends, family, or colleagues.</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F5F3FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 6px 0;font-weight:700;color:#7C3AED;">Step 2</p><p style="margin:0;font-size:14px;color:#64748B;">When they sign up and make their first purchase, you both earn $20.</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;color:#475569;">There is no limit to how many friends you can refer. The more you share, the more you earn. Your referral credits never expire and can be applied to any purchase.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Share Your Referral Link',
                            'url' => 'https://example.com/referral',
                            'bg_color' => '#7C3AED',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Track your referrals and credits anytime from your account dashboard. Terms and conditions apply.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from marketing emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 14. Webinar Registration Confirmation (Engagement)
            // ---------------------------------------------------------------
            [
                'name' => 'Webinar Registration Confirmation',
                'category' => 'engagement',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#4F46E5',
                        ],
                    ],
                    [
                        'type' => 'spacer',
                        'data' => ['height' => '12'],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">You Are Registered, {first_name}!</h2><p style="margin:0 0 12px 0;color:#475569;">Your spot for the upcoming webinar has been confirmed. We are looking forward to having you join the conversation. Here are the details you will need.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EEF2FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#4F46E5;font-size:13px;text-transform:uppercase;">Topic</p><p style="margin:0;font-size:14px;color:#475569;">Scaling Your Business<br>with AI Automation</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#EEF2FF;border-radius:8px;"><tr><td style="padding:16px;text-align:center;"><p style="margin:0 0 4px 0;font-weight:700;color:#4F46E5;font-size:13px;text-transform:uppercase;">Date &amp; Time</p><p style="margin:0;font-size:14px;color:#475569;">Wednesday, April 23, 2026<br>1:00 PM - 2:30 PM EST</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">What to Expect</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Expert panel discussion with industry leaders and practitioners.</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; Live demo of practical AI tools you can implement today.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; Audience Q&amp;A session at the end of the presentation.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Add to Calendar',
                            'url' => 'https://example.com/webinar/calendar',
                            'bg_color' => '#4F46E5',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">A join link will be sent 30 minutes before the webinar begins. If you can not make it live, a recording will be shared with all registrants within 24 hours.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from webinar emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 15. Case Study Highlight (Marketing)
            // ---------------------------------------------------------------
            [
                'name' => 'Case Study Highlight',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#0F766E',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x250/0F766E/FFFFFF?text=Client+Success+Story',
                            'alt' => 'Case study success story banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;">How Pinnacle Corp Grew Revenue by 140%</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, we love sharing stories of customers who are achieving remarkable results. Pinnacle Corp partnered with {company} twelve months ago, and the transformation speaks for itself.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F0FDFA;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#0F766E;">140%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Revenue Growth</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F0FDFA;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#0F766E;">3x</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Customer Retention</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;color:#475569;"><em>"Switching to {company} was a turning point for our business. The automation tools alone saved us 20 hours per week, and the analytics gave us insights we never had before. It paid for itself in the first month."</em><br><strong style="color:#0F766E;">&mdash; Jessica Tran, VP of Marketing, Pinnacle Corp</strong></p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Read the Full Case Study',
                            'url' => 'https://example.com/case-studies/pinnacle',
                            'bg_color' => '#0F766E',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Want results like these? Schedule a free strategy call with our team and discover what is possible for your business.</p>',
                            'align' => 'center',
                            'font_size' => '14',
                        ],
                    ],
                    [
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from marketing emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 16. New Year Sale (Seasonal/Marketing)
            // ---------------------------------------------------------------
            [
                'name' => 'New Year Sale',
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
                            'src' => 'https://placehold.co/600x280/DC2626/FFFFFF?text=New+Year+%7C+New+Deals',
                            'alt' => 'New Year sale banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">New Year, New Savings!</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, start the year strong with exclusive deals from {company}. Whether you are upgrading your toolkit or trying something new, now is the perfect time to invest in yourself.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FEF2F2;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#DC2626;">30%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Off All Plans</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#FEF2F2;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#DC2626;">FREE</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Setup &amp; Onboarding</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;color:#475569;text-align:center;">Use code <strong style="color:#DC2626;">NEWYEAR2026</strong> at checkout. Offer valid through January 31, 2026. Cannot be combined with other promotions.</p>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Shop Now',
                            'url' => 'https://example.com/new-year-sale',
                            'bg_color' => '#DC2626',
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
                        'type' => 'footer',
                        'data' => [
                            'text' => '{company} | 123 Business Street, Suite 100',
                            'unsubscribe_text' => 'Unsubscribe from promotional emails',
                        ],
                    ],
                ],
            ],

            // ---------------------------------------------------------------
            // 17. Summer Collection Launch (Seasonal/Marketing)
            // ---------------------------------------------------------------
            [
                'name' => 'Summer Collection Launch',
                'category' => 'marketing',
                'blocks' => [
                    [
                        'type' => 'header',
                        'data' => [
                            'logo_url' => '',
                            'company_name' => '{company}',
                            'bg_color' => '#0EA5E9',
                        ],
                    ],
                    [
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x300/0EA5E9/FFFFFF?text=Summer+Collection+2026',
                            'alt' => 'Summer collection launch banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">The Summer Collection is Here</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, sunshine is calling and so is our brand new summer lineup. Designed with comfort, style, and warm-weather living in mind, these new arrivals are ready to elevate your season.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<h3 style="margin:0 0 8px 0;color:#0EA5E9;">Breezy Essentials</h3><p style="margin:0;font-size:14px;color:#64748B;">Lightweight fabrics, relaxed fits, and effortless colors that take you from the beach to dinner without missing a beat.</p>',
                            'right_content' => '<h3 style="margin:0 0 8px 0;color:#0EA5E9;">Outdoor Ready</h3><p style="margin:0;font-size:14px;color:#64748B;">Durable, UV-protective gear built for adventure. From hiking trails to weekend getaways, we have you covered.</p>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0;color:#475569;text-align:center;">Early access members enjoy free shipping on all summer collection orders placed this week. Do not miss out on the freshest styles of the season.</p>',
                            'align' => 'center',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Shop the Summer Collection',
                            'url' => 'https://example.com/summer-collection',
                            'bg_color' => '#0EA5E9',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Free returns within 30 days. Hassle-free exchanges on all items. Shop with confidence.</p>',
                            'align' => 'center',
                            'font_size' => '14',
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
            // 18. Black Friday Preview (Seasonal/Marketing)
            // ---------------------------------------------------------------
            [
                'name' => 'Black Friday Preview',
                'category' => 'marketing',
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
                        'type' => 'image',
                        'data' => [
                            'src' => 'https://placehold.co/600x280/1E293B/FFFFFF?text=Black+Friday+%7C+Exclusive+Preview',
                            'alt' => 'Black Friday exclusive preview banner',
                            'width' => '100',
                            'link_url' => '',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<h2 style="margin:0 0 16px 0;color:#1E293B;text-align:center;">Black Friday Starts Early for You</h2><p style="margin:0 0 12px 0;color:#475569;">Hi {first_name}, as a valued {company} member, you get exclusive early access to our biggest sale of the year. These deals go live for everyone on Friday, but you can shop them right now.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'columns',
                        'data' => [
                            'left_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F1F5F9;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#1E293B;">50%</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Off Select Items</p></td></tr></table>',
                            'right_content' => '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#F1F5F9;border-radius:8px;"><tr><td style="padding:20px;text-align:center;"><p style="margin:0;font-size:32px;font-weight:700;color:#1E293B;">48h</p><p style="margin:4px 0 0 0;font-size:13px;color:#64748B;text-transform:uppercase;">Early Access Window</p></td></tr></table>',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'data' => [
                            'content' => '<p style="margin:0 0 8px 0;font-weight:700;color:#1E293B;">Top Deals to Watch</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; <strong>Pro Annual Plan:</strong> 50% off the first year (normally $299, now $149).</p><p style="margin:0 0 4px 0;font-size:14px;color:#475569;">&#8226; <strong>Team Bundle:</strong> Buy 3 seats, get 2 free for 12 months.</p><p style="margin:0;font-size:14px;color:#475569;">&#8226; <strong>Premium Add-Ons:</strong> All add-ons at 40% off, no code needed.</p>',
                            'align' => 'left',
                            'font_size' => '16',
                        ],
                    ],
                    [
                        'type' => 'button',
                        'data' => [
                            'text' => 'Preview the Deals',
                            'url' => 'https://example.com/black-friday',
                            'bg_color' => '#1E293B',
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
                            'content' => '<p style="margin:0;font-size:14px;color:#94A3B8;text-align:center;">Early access expires in 48 hours. After that, deals open to the public and quantities are limited. Do not wait.</p>',
                            'align' => 'center',
                            'font_size' => '14',
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
