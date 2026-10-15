<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // Return empty dashboard immediately if DB queries fail
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return view('admin.dashboard', $this->emptyData());
        }

        // Cache admin dashboard for 60 seconds to reduce DB load
        $data = cache()->remember('admin:dashboard', 60, function () {
            return $this->loadDashboardData();
        });

        return view('admin.dashboard', $data);
    }

    private function loadDashboardData(): array
    {
        $data = $this->emptyData();

        // Combine user counts into a single query
        $userStats = $this->q(fn () => DB::table('users')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active")
            ->selectRaw("SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as this_month", [now()->month, now()->year])
            ->selectRaw("SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN 1 ELSE 0 END) as last_month", [now()->subMonth()->month, now()->subMonth()->year])
            ->first(), (object) ['total' => 0, 'active' => 0, 'this_month' => 0, 'last_month' => 0]);

        $data['totalUsers'] = $userStats->total;
        $data['activeUsers'] = $userStats->active;
        $data['totalWorkspaces'] = $this->q(fn () => DB::table('workspaces')->count());
        $data['newUsersThisMonth'] = $userStats->this_month;
        $data['newUsersLastMonth'] = $userStats->last_month;
        $data['userGrowth'] = $data['newUsersLastMonth'] > 0
            ? round((($data['newUsersThisMonth'] - $data['newUsersLastMonth']) / $data['newUsersLastMonth']) * 100, 1) : 0;

        // Combine revenue queries into single query
        $revenueStats = $this->q(fn () => DB::table('payments')->where('status', 'succeeded')
            ->selectRaw("SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN amount ELSE 0 END) as this_month", [now()->month, now()->year])
            ->selectRaw("SUM(CASE WHEN MONTH(created_at) = ? AND YEAR(created_at) = ? THEN amount ELSE 0 END) as last_month", [now()->subMonth()->month, now()->subMonth()->year])
            ->first(), (object) ['this_month' => 0, 'last_month' => 0]);

        $data['revenueThisMonth'] = $revenueStats->this_month ?? 0;
        $data['revenueLastMonth'] = $revenueStats->last_month ?? 0;
        $data['revenueGrowth'] = $data['revenueLastMonth'] > 0
            ? round((($data['revenueThisMonth'] - $data['revenueLastMonth']) / $data['revenueLastMonth']) * 100, 1) : 0;

        $data['mrr'] = $this->q(fn () => DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', 'active')
            ->selectRaw("SUM(CASE WHEN subscriptions.billing_cycle = 'yearly' THEN plans.yearly_price / 12 ELSE plans.monthly_price END) as mrr")
            ->value('mrr') ?? 0);
        $data['arr'] = $data['mrr'] * 12;

        $data['planDistribution'] = $this->q(fn () => DB::table('subscriptions')
            ->join('plans', 'plans.id', '=', 'subscriptions.plan_id')
            ->where('subscriptions.status', 'active')
            ->selectRaw('plans.name, COUNT(*) as cnt')
            ->groupBy('plans.name')
            ->pluck('cnt', 'name'), collect());

        $data['aiUsageThisMonth'] = $this->q(fn () => DB::table('ai_usage_logs')
            ->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)
            ->selectRaw("COUNT(*) as total_requests, COALESCE(SUM(tokens_in + tokens_out), 0) as total_tokens, COALESCE(SUM(cost), 0) as total_cost")
            ->first(), (object) ['total_requests' => 0, 'total_tokens' => 0, 'total_cost' => 0]);

        // P-02: Eager load currentPlan to avoid N+1 queries in the dashboard view
        $data['recentUsers'] = $this->q(fn () => \App\Models\User::with('currentPlan')
            ->latest()->limit(10)->get(), collect());

        $data['recentPayments'] = $this->q(fn () => \App\Models\Payment::with(['workspace', 'subscription.plan'])
            ->orderByDesc('created_at')->limit(10)->get(), collect());

        // Monthly trends
        $sixAgo = now()->subMonths(5)->startOfMonth();
        $data['monthlyRevenue'] = $this->q(function () use ($sixAgo) {
            $map = DB::table('payments')->where('status', 'succeeded')->where('created_at', '>=', $sixAgo)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, SUM(amount) as t")->groupBy('ym')->pluck('t', 'ym');
            return $this->buildMonths($map, 'revenue');
        }, $this->buildMonths(collect(), 'revenue'));

        $data['monthlySignups'] = $this->q(function () use ($sixAgo) {
            $map = DB::table('users')->where('created_at', '>=', $sixAgo)
                ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as t")->groupBy('ym')->pluck('t', 'ym');
            return $this->buildMonths($map, 'count');
        }, $this->buildMonths(collect(), 'count'));

        $data['openTicketsCount'] = $this->q(fn () => DB::table('tickets')->whereIn('status', ['open', 'in_progress'])->count());
        $data['recentTickets'] = $this->q(fn () => DB::table('tickets')->whereIn('status', ['open', 'in_progress'])
            ->orderByDesc('created_at')->limit(5)->get(['id', 'subject', 'priority', 'status', 'created_at']), collect());

        $data['payingUsers'] = $this->q(fn () => DB::table('subscriptions')->where('status', 'active')
            ->distinct()->count('workspace_id'));
        $data['churnRate'] = $data['payingUsers'] > 0
            ? round(($this->q(fn () => DB::table('subscriptions')->where('status', 'cancelled')
                ->whereMonth('updated_at', now()->month)->whereYear('updated_at', now()->year)->count()) / $data['payingUsers']) * 100, 1) : 0;

        // System Snapshot (system overview)
        $data['systemSnapshot'] = [
            'two_factor' => $this->q(fn () => DB::table('users')->where('is_admin', true)->where('two_factor_enabled', true)->count()) . '/' . $this->q(fn () => DB::table('users')->where('is_admin', true)->count()),
            'blocked_ips' => $this->q(fn () => DB::table('blocked_ips')->where(function ($q) { $q->whereNull('blocked_until')->orWhere('blocked_until', '>', now()); })->count()),
            'queue_pending' => $this->q(fn () => DB::table('jobs')->count()),
            'failed_jobs' => $this->q(fn () => DB::table('failed_jobs')->count()),
            'email_accounts' => $this->q(fn () => DB::table('email_accounts')->where('status', 'connected')->count()),
            'social_login' => config('services.google.client_id') ? 'Enabled' : 'Disabled',
        ];

        // Recent Activity (system overview)
        $data['recentActivity'] = $this->q(fn () => DB::table('audit_logs')
            ->orderByDesc('created_at')
            ->limit(8)
            ->get(['event', 'actor_name', 'auditable_type', 'auditable_id', 'ip_address', 'created_at'])
            ->map(fn ($log) => [
                'user' => $log->actor_name ?? 'System',
                'action' => str_replace('_', ' ', ucfirst($log->event)),
                'description' => $log->auditable_type ? class_basename($log->auditable_type) . ' #' . $log->auditable_id : '',
                'time' => \Carbon\Carbon::parse($log->created_at)->diffForHumans(short: true),
            ]), collect());

        return $data;
    }

    private function q(callable $fn, mixed $default = 0): mixed
    {
        try { return $fn() ?? $default; } catch (\Throwable) { return $default; }
    }

    private function buildMonths($map, string $key): array
    {
        $r = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i);
            $r[] = ['month' => $m->format('M'), $key => (float) ($map[$m->format('Y-m')] ?? 0)];
        }
        return $r;
    }

    private function emptyData(): array
    {
        return [
            'mrr' => 0, 'arr' => 0, 'revenueThisMonth' => 0, 'revenueLastMonth' => 0, 'revenueGrowth' => 0,
            'totalUsers' => 0, 'activeUsers' => 0, 'newUsersThisMonth' => 0, 'userGrowth' => 0,
            'totalWorkspaces' => 0, 'planDistribution' => collect(), 'openTicketsCount' => 0,
            'aiUsageThisMonth' => (object) ['total_requests' => 0, 'total_tokens' => 0, 'total_cost' => 0],
            'recentUsers' => collect(), 'recentPayments' => collect(), 'recentTickets' => collect(),
            'monthlyRevenue' => $this->buildMonths(collect(), 'revenue'),
            'monthlySignups' => $this->buildMonths(collect(), 'count'),
            'payingUsers' => 0, 'churnRate' => 0,
            'systemSnapshot' => ['two_factor' => '0/0', 'blocked_ips' => 0, 'queue_pending' => 0, 'failed_jobs' => 0, 'email_accounts' => 0, 'social_login' => 'Disabled'],
            'recentActivity' => collect(),
        ];
    }
}
