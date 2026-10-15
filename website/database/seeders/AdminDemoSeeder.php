<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * AdminDemoSeeder — populates the admin console at /admin so the
 * super-admin sees a fully-populated dashboard for demo purposes:
 *
 *   • 18 demo users (with workspaces + memberships)
 *   • Active/trialing/canceled subscriptions distributed across all plans
 *     (Starter / Pro / Enterprise) → drives Plan Distribution + Active
 *     Subscriptions card + conversion %
 *   • 80 payments spread across the last 6 months → drives Total Revenue,
 *     This Month, Recent Payments table, and Revenue Trend chart
 *   • 8 demo coupons (mix of percent / amount, monthly / forever)
 *   • 15 support tickets (open / in_progress / waiting / resolved)
 *     with replies → drives Open Tickets card + Pending Tickets footer
 *   • AI usage records for the current month → drives AI Replies (MTD)
 *
 * Idempotent-ish: re-running adds MORE demo users/payments. To reset the
 * admin view, delete users created by this seeder (their email pattern
 * is firstname.lastname.NNN@demo.mailtrixy.test) and the cascading data
 * cleans up via foreign-key constraints.
 *
 * Usage on the server (one-time, after install):
 *   php artisan db:seed --class=AdminDemoSeeder --force
 */
class AdminDemoSeeder extends Seeder
{
    /** Email domain used for all generated demo accounts. */
    private const DEMO_DOMAIN = 'demo.mailtrixy.test';

    /** How many fake users / workspaces to create. */
    private const USER_COUNT = 18;

    /** How many payment rows to spread across the last 6 months. */
    private const PAYMENT_COUNT = 80;

    private const FIRST_NAMES = [
        'Aarav', 'Priya', 'Rahul', 'Ananya', 'Vikram', 'Neha', 'Arjun', 'Riya',
        'Sarah', 'Michael', 'Emily', 'James', 'Olivia', 'Daniel', 'Sophia', 'Liam',
        'Marco', 'Lucia',
    ];
    private const LAST_NAMES = [
        'Sharma', 'Patel', 'Kumar', 'Singh', 'Mehta', 'Desai', 'Reddy', 'Iyer',
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Davis', 'Miller', 'Wilson',
        'Garcia', 'Rossi',
    ];
    private const COMPANIES = [
        'Acme Corp', 'TechHub Inc', 'Bloomly', 'Mintly', 'Sundae Labs', 'Lumos AI',
        'PixelForge', 'Cloud Nimbus', 'Velocity Stack', 'Northwind', 'Globex Co',
        'Initech', 'Hooli', 'Pied Piper', 'Stark Industries', 'Wayne Enterprises',
        'Tyrell Corp', 'Wonka Industries',
    ];

    private const TICKET_SUBJECTS = [
        'Cannot connect Gmail account',         'Billing question — last invoice',
        'Feature request: dark mode',           'Campaign send is stuck on "sending"',
        'How do I import contacts from CSV?',   'API rate limit too low',
        'Refund request',                       '2FA not working on mobile',
        'Custom domain SSL cert expired',       'Webhook deliveries failing',
        'Workspace deletion — can I recover?',  'Pricing for 100k contacts',
        'AI auto-reply replying to bounces',    'White-label setup help',
        'Integration with HubSpot — possible?',
    ];

    public function run(): void
    {
        $this->command?->info('Seeding admin dashboard demo data…');

        DB::transaction(function () {
            $userIds = $this->seedUsersAndWorkspaces(self::USER_COUNT);
            $this->command?->info('  ✓ ' . count($userIds) . ' demo users + workspaces');

            $subs = $this->seedSubscriptions($userIds);
            $this->command?->info('  ✓ ' . count($subs) . ' subscriptions across plans');

            $coupons = $this->seedCoupons();
            $this->command?->info('  ✓ ' . count($coupons) . ' demo coupons');

            $payments = $this->seedPayments(self::PAYMENT_COUNT, $subs, $coupons);
            $this->command?->info('  ✓ ' . $payments . ' payments over last 6 months');

            $tickets = $this->seedTickets($userIds);
            $this->command?->info('  ✓ ' . $tickets . ' support tickets with replies');

            $ai = $this->seedAiUsage($userIds);
            $this->command?->info('  ✓ ' . $ai . ' AI usage records (current month)');
        });

        $this->command?->info('Done. Refresh /admin to see the populated dashboard.');
    }

    /** @return array<int, array{user_id:int,workspace_id:int}> */
    private function seedUsersAndWorkspaces(int $count): array
    {
        $out = [];
        $now = now();
        for ($i = 0; $i < $count; $i++) {
            $first = self::FIRST_NAMES[array_rand(self::FIRST_NAMES)];
            $last  = self::LAST_NAMES[array_rand(self::LAST_NAMES)];
            $rand  = random_int(100, 9999);
            $email = strtolower("{$first}.{$last}.{$rand}@" . self::DEMO_DOMAIN);
            $createdAt = $now->copy()->subDays(random_int(0, 180));

            $userId = DB::table('users')->insertGetId([
                'uuid'              => (string) Str::uuid(),
                'name'              => "{$first} {$last}",
                'email'             => $email,
                'email_verified_at' => $createdAt,
                'password'          => Hash::make('demo1234'),
                'phone'             => '+1' . random_int(2000000000, 9999999999),
                'timezone'          => 'UTC',
                'locale'            => 'en',
                'language'          => 'en',
                'currency_code'     => 'USD',
                'status'            => 'active',
                'is_admin'          => 0,
                'created_at'        => $createdAt,
                'updated_at'        => $createdAt,
            ]);

            $company = self::COMPANIES[array_rand(self::COMPANIES)];
            $wsId = DB::table('workspaces')->insertGetId([
                'uuid'                  => (string) Str::uuid(),
                'name'                  => $company,
                'slug'                  => Str::slug($company) . '-' . $rand,
                'industry'              => 'SaaS',
                'team_size'             => ['1-10','11-50','51-200','200+'][array_rand(['1-10','11-50','51-200','200+'])],
                'timezone'              => 'UTC',
                'onboarding_completed'  => 1,
                'onboarding_step'       => 0,
                'created_at'            => $createdAt,
                'updated_at'            => $createdAt,
            ]);

            DB::table('workspace_members')->insert([
                'workspace_id' => $wsId,
                'user_id'      => $userId,
                'role'         => 'owner',
                'status'       => 'active',
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ]);

            DB::table('users')->where('id', $userId)->update(['active_workspace_id' => $wsId]);

            $out[] = ['user_id' => $userId, 'workspace_id' => $wsId, 'created_at' => $createdAt];
        }
        return $out;
    }

    /**
     * Distribute subscriptions across plans (skip the free tier).
     * 60% active, 20% trialing, 15% canceled, 5% past_due — roughly realistic.
     *
     * @return array<int, array{id:int, workspace_id:int, plan_id:int, billing_cycle:string, amount:float, status:string, created_at:\Illuminate\Support\Carbon}>
     */
    private function seedSubscriptions(array $users): array
    {
        $paidPlans = DB::table('plans')
            ->whereIn('slug', ['starter', 'pro', 'enterprise'])
            ->where('is_active', 1)
            ->get(['id', 'slug', 'monthly_price', 'yearly_price'])
            ->keyBy('id');

        if ($paidPlans->isEmpty()) return [];

        $statusBuckets = array_merge(
            array_fill(0, 12, 'active'),
            array_fill(0, 4, 'trialing'),
            array_fill(0, 3, 'canceled'),
            array_fill(0, 1, 'past_due'),
        );

        $out = [];
        foreach ($users as $u) {
            $plan = $paidPlans->random();
            $cycle = random_int(0, 4) === 0 ? 'yearly' : 'monthly';
            $status = $statusBuckets[array_rand($statusBuckets)];
            $amount = $cycle === 'yearly' ? (float) $plan->yearly_price : (float) $plan->monthly_price;
            $startedAt = $u['created_at'];
            $periodEnd = $cycle === 'yearly'
                ? $startedAt->copy()->addYear()
                : $startedAt->copy()->addMonth();

            $subId = DB::table('subscriptions')->insertGetId([
                'workspace_id'           => $u['workspace_id'],
                'plan_id'                => $plan->id,
                'stripe_subscription_id' => 'sub_demo_' . Str::random(16),
                'stripe_customer_id'     => 'cus_demo_' . Str::random(16),
                'status'                 => $status,
                'billing_cycle'          => $cycle,
                'trial_ends_at'          => $status === 'trialing' ? now()->addDays(random_int(2, 14)) : null,
                'current_period_start'   => $startedAt,
                'current_period_end'     => $periodEnd,
                'canceled_at'            => $status === 'canceled' ? now()->subDays(random_int(1, 30)) : null,
                'created_at'             => $startedAt,
                'updated_at'             => $startedAt,
            ]);

            $out[] = [
                'id'             => $subId,
                'workspace_id'   => $u['workspace_id'],
                'plan_id'        => $plan->id,
                'billing_cycle'  => $cycle,
                'amount'         => $amount,
                'status'         => $status,
                'created_at'     => $startedAt,
            ];
        }
        return $out;
    }

    /** @return array<int> coupon ids */
    private function seedCoupons(): array
    {
        $defs = [
            ['LAUNCH50',   'Launch promo — 50% off',     'percent_off',   50,   null, 'once',     null, 200, 47],
            ['BLACKFRIDAY','Black Friday',                'percent_off',   30,   null, 'once',     null, 500, 132],
            ['LIFETIME20', 'Lifetime 20% off',            'percent_off',   20,   null, 'forever',  null, null, 18],
            ['SAVE10',     '$10 off first month',         'amount_off',    null, 1000, 'once',     null, 1000, 88],
            ['VIP15',      'VIP partner discount',        'percent_off',   15,   null, 'repeating', 6,   100, 22],
            ['FRIENDS25',  'Friends & family',            'percent_off',   25,   null, 'forever',  null, 50,  11],
            ['SUMMER20',   'Summer sale',                 'percent_off',   20,   null, 'once',     null, 300, 0],
            ['UPGRADE100', '$100 off Enterprise',         'amount_off',    null, 10000,'once',     null, 50,  3],
        ];

        $out = [];
        foreach ($defs as [$code, $name, $type, $pct, $amt, $duration, $months, $maxUses, $used]) {
            $id = DB::table('coupons')->insertGetId([
                'code'              => $code,
                'name'              => $name,
                'type'              => $type,
                'percent_off'       => $pct,
                'amount_off'        => $amt,
                'value'             => $pct ?? ($amt / 100),
                'currency'          => 'USD',
                'duration'          => $duration,
                'duration_in_months'=> $months,
                'max_redemptions'   => $maxUses,
                'times_redeemed'    => $used,
                'max_uses'          => $maxUses,
                'times_used'        => $used,
                'expires_at'        => now()->addDays(random_int(30, 365)),
                'is_active'         => 1,
                'created_at'        => now()->subDays(random_int(7, 90)),
                'updated_at'        => now(),
            ]);
            $out[] = $id;
        }
        return $out;
    }

    /**
     * Spread payments across the last 6 months. The distribution biases
     * toward "this month" so the dashboard's "This Month" card lights up.
     */
    private function seedPayments(int $count, array $subs, array $couponIds): int
    {
        if (empty($subs)) return 0;
        $codes = DB::table('coupons')->whereIn('id', $couponIds)->pluck('code')->all();
        $statusBuckets = array_merge(
            array_fill(0, 18, 'succeeded'),
            array_fill(0, 1,  'failed'),
            array_fill(0, 1,  'pending'),
        );

        $created = 0;
        for ($i = 0; $i < $count; $i++) {
            $sub = $subs[array_rand($subs)];
            // Weight: 35% this month, 65% spread over the previous 5 months
            $monthOffset = random_int(0, 9) < 4 ? 0 : random_int(1, 5);
            $paidAt = now()->subMonths($monthOffset)
                ->subDays(random_int(0, 27))
                ->subHours(random_int(0, 23));

            $useCoupon = !empty($codes) && random_int(0, 4) === 0;
            $couponCode = $useCoupon ? $codes[array_rand($codes)] : null;
            $original = $sub['amount'];
            $discount = $useCoupon ? round($original * 0.2, 2) : 0;
            $final    = max($original - $discount, 0.50);
            $status   = $statusBuckets[array_rand($statusBuckets)];

            DB::table('payments')->insert([
                'workspace_id'        => $sub['workspace_id'],
                'subscription_id'     => $sub['id'],
                'stripe_payment_id'   => 'pi_demo_' . Str::random(20),
                'stripe_invoice_id'   => 'in_demo_' . Str::random(20),
                'amount'              => $final,
                'currency'            => 'USD',
                'status'              => $status,
                'gateway_transaction_id' => 'txn_' . Str::random(16),
                'gateway_slug'        => 'stripe',
                'description'         => 'Subscription renewal — ' . ($sub['billing_cycle'] === 'yearly' ? 'Annual' : 'Monthly'),
                'coupon_code'         => $couponCode,
                'discount_amount'     => $discount > 0 ? $discount : null,
                'original_amount'     => $useCoupon ? $original : null,
                'failure_reason'      => $status === 'failed' ? 'Card declined (insufficient funds)' : null,
                'created_at'          => $paidAt,
                'updated_at'          => $paidAt,
            ]);
            $created++;
        }
        return $created;
    }

    private function seedTickets(array $users): int
    {
        $statusBuckets = ['open', 'open', 'open', 'in_progress', 'in_progress', 'waiting', 'resolved', 'resolved'];
        $priorities   = ['low', 'normal', 'normal', 'high', 'urgent'];
        $categories   = ['billing', 'technical', 'feature_request', 'bug', 'general'];

        $created = 0;
        $userIds = array_column($users, 'user_id');
        $workspaceMap = array_column($users, 'workspace_id', 'user_id');

        foreach (self::TICKET_SUBJECTS as $i => $subject) {
            $userId = $userIds[array_rand($userIds)];
            $createdAt = now()->subDays(random_int(0, 30))->subHours(random_int(0, 23));
            $status = $statusBuckets[array_rand($statusBuckets)];

            $ticketId = DB::table('tickets')->insertGetId([
                'user_id'      => $userId,
                'workspace_id' => $workspaceMap[$userId] ?? null,
                'subject'      => $subject,
                'body'         => "Hi support,\n\n" . $subject . ". Could someone take a look when you get a chance?\n\nThanks!",
                'status'       => $status,
                'priority'     => $priorities[array_rand($priorities)],
                'category'     => $categories[array_rand($categories)],
                'assigned_to'  => $status === 'open' ? null : 1, // assign to admin#1 once worked
                'created_at'   => $createdAt,
                'updated_at'   => $createdAt,
            ]);
            $created++;

            // Add 0-3 replies; resolved/in_progress tickets get an admin reply.
            $replyCount = $status === 'open' ? random_int(0, 1) : random_int(1, 3);
            for ($r = 0; $r < $replyCount; $r++) {
                $isAdmin = $r % 2 === 1;
                DB::table('ticket_replies')->insert([
                    'ticket_id'      => $ticketId,
                    'user_id'        => $isAdmin ? 1 : $userId,
                    'body'           => $isAdmin
                        ? "Thanks for reaching out — looking into this now. Will follow up shortly."
                        : "Any update? This is becoming a blocker for us.",
                    'is_admin_reply' => $isAdmin ? 1 : 0,
                    'created_at'     => $createdAt->copy()->addHours($r + 1),
                    'updated_at'     => $createdAt->copy()->addHours($r + 1),
                ]);
            }
        }
        return $created;
    }

    /**
     * Seed AI usage so the dashboard's "AI Replies (MTD)" card shows a
     * non-zero count for the current month. Uses the usage_records table
     * with feature_key='ai_replies' grouped by current YYYY-MM period.
     */
    private function seedAiUsage(array $users): int
    {
        $period = now()->format('Y-m');
        $created = 0;
        foreach ($users as $u) {
            DB::table('usage_records')->insert([
                'workspace_id' => $u['workspace_id'],
                'feature_key'  => 'ai_replies',
                'quantity'     => random_int(20, 350),
                'period'       => $period,
                'created_at'   => now()->subDays(random_int(0, 27)),
                'updated_at'   => now(),
            ]);
            $created++;
        }
        return $created;
    }
}
