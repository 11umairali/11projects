<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mailbox\SendingLimit;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SendingLimitsController extends Controller
{
    public function index()
    {
        $vals = [];
        foreach (array_keys(SendingLimit::KEYS) as $k) $vals[$k] = SendingLimit::value($k);
        $top = DB::table('sent_messages as s')->leftJoin('users as u', 'u.id', '=', 's.user_id')
            ->where('s.created_at', '>=', now()->subDay())->groupBy('s.user_id', 'u.email')
            ->orderByRaw('SUM(s.recipients) desc')->limit(15)->get(['u.email', DB::raw('SUM(s.recipients) as n')]);
        return view('admin.sending-limits', ['v' => $vals, 'top' => $top]);
    }

    public function update(Request $r)
    {
        $d = $r->validate(array_fill_keys(array_keys(SendingLimit::KEYS), 'required|integer|min:0|max:1000000'));
        foreach ($d as $k => $v) SystemSetting::set($k, (string) $v, 'sending');
        return redirect('/admin/sending-limits')->with('success', 'Sending limits saved. They apply immediately.');
    }
}
