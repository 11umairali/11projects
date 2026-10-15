<?php
namespace App\Jobs;
use App\Models\{AdminBroadcast, User};
use App\Notifications\AdminBroadcastNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\DB;
class DeliverAdminBroadcast implements ShouldQueue, \Illuminate\Contracts\Queue\ShouldBeUnique {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $uniqueFor = 600;
    public function uniqueId(): string { return 'admin-broadcast-'.$this->deliveryId; }
    public int $tries = 1;
    public int $timeout = 60;
    public function __construct(public int $deliveryId) { $this->onQueue('default'); }
    public function handle(): void {
        $d = DB::table('admin_broadcast_deliveries')->find($this->deliveryId);
        if (!$d) return;
        $b = AdminBroadcast::find($d->broadcast_id); $user = User::find($d->user_id);
        if (!$b || !$user) return;
        foreach (['push' => 'database', 'email' => 'mail'] as $kind => $channel) {
            $column = $kind.'_status';
            // Atomic claim prevents redelivery by duplicate workers. Ambiguous provider failures are never retried automatically.
            if (!DB::table('admin_broadcast_deliveries')->where('id', $d->id)->where($column, 'pending')->update([$column => 'sending', 'updated_at' => now()])) continue;
            $prefs = $user->notification_preferences ?? [];
            $allowed = ($prefs[$kind === 'push' ? 'inAppNotifs' : 'emailNotifs'] ?? true) !== false && ($prefs['events']['announcement_'.$b->category][$kind === 'push' ? 'inApp' : 'email'] ?? true) !== false;
            if (!$b->$kind || !$allowed || $user->status !== 'active') { $this->status($column, 'skipped'); continue; }
            try { $user->notify(new AdminBroadcastNotification($b, $channel)); $this->status($column, $kind === 'push' ? 'submitted' : 'sent'); }
            catch (\Throwable $e) { $this->status($column, 'failed'); report($e); }
        }
        $this->finish($b->id);
    }
    private function status(string $column, string $value): void { DB::table('admin_broadcast_deliveries')->where('id', $this->deliveryId)->update([$column => $value, 'updated_at' => now()]); }
    private function finish(int $id): void {
        $pending = DB::table('admin_broadcast_deliveries')->where('broadcast_id', $id)->where(fn ($q) => $q->whereIn('push_status', ['pending', 'sending'])->orWhereIn('email_status', ['pending', 'sending']))->exists();
        if (!$pending) AdminBroadcast::whereKey($id)->update(['status' => 'completed']);
    }
    public function failed(?\Throwable $e): void {
        $d = DB::table('admin_broadcast_deliveries')->find($this->deliveryId);
        if (!$d) return;
        foreach (['push_status', 'email_status'] as $col) DB::table('admin_broadcast_deliveries')->where('id', $d->id)->whereIn($col, ['pending', 'sending'])->update([$col => 'failed']);
        $this->finish($d->broadcast_id);
    }
}
