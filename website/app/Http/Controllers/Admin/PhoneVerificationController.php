<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use App\Support\PhoneSettings;
use Illuminate\Http\Request;

/** Admin → Phone Verification. SUPER ADMIN ONLY. */
class PhoneVerificationController extends Controller
{
    private function guard(): void
    {
        $u = auth()->user();
        abort_unless($u && $u->is_admin && ($u->admin_role === 'super_admin' || $u->hasRole('Super Admin')), 403, 'Only a super admin can change phone verification.');
    }

    public function index()
    {
        $this->guard();
        return view('admin.phone-verification', [
            'registration' => PhoneSettings::registrationMode(),
            'verification' => PhoneSettings::verificationEnabled(),
            'unverifiedDiscovery' => PhoneSettings::unverifiedDiscovery(),
            'sms' => PhoneSettings::smsEnabled(),
            'whatsapp' => PhoneSettings::whatsappEnabled(),
            'smsReady' => PhoneSettings::smsConfigured(),
            'whatsappReady' => PhoneSettings::whatsappConfigured(),
            'template' => SystemSetting::get('whatsapp_otp_template'),
            'language' => SystemSetting::get('whatsapp_otp_language', 'en_US') ?: 'en_US',
            'button' => SystemSetting::get('whatsapp_otp_button', '1') === '1',
            'channels' => PhoneSettings::channels(),
            'testMode' => PhoneSettings::testMode(),
        ]);
    }

    public function update(Request $request)
    {
        $this->guard();
        $d = $request->validate([
            'phone_registration' => 'required|in:off,optional,required',
            'whatsapp_otp_template' => 'nullable|string|max:120',
            'whatsapp_otp_language' => 'nullable|string|max:12',
        ]);
        SystemSetting::set('phone_registration', $d['phone_registration'], 'phone');
        foreach (['phone_verification_enabled', 'phone_verify_sms', 'phone_verify_whatsapp', 'whatsapp_otp_button', 'phone_discovery_unverified'] as $k) {
            SystemSetting::set($k, $request->boolean($k) ? '1' : '0', 'phone');
        }
        SystemSetting::set('whatsapp_otp_template', trim((string) ($d['whatsapp_otp_template'] ?? '')), 'phone');
        SystemSetting::set('whatsapp_otp_language', trim((string) ($d['whatsapp_otp_language'] ?? '')) ?: 'en_US', 'phone');
        return redirect()->route('admin.phone-verification')->with('success', 'Phone settings saved.');
    }
}
