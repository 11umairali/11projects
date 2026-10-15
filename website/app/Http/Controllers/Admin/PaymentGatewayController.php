<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;
use App\Services\Payment\PaymentGatewayManager;
use Illuminate\Http\Request;

class PaymentGatewayController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $manager,
    ) {}

    /**
     * Display all payment gateways with status toggles and configure buttons.
     */
    public function index()
    {
        $gateways = PaymentGateway::orderBy('sort_order')->get();

        // Attach credential fields + decrypted values to each gateway
        $gateways->each(function ($gateway) {
            if ($this->manager->isSupported($gateway->slug)) {
                $gateway->setAttribute('credentialFields', $this->manager->credentialFieldsFor($gateway->slug));
            } else {
                $gateway->setAttribute('credentialFields', []);
            }

            $gateway->setAttribute('decryptedCredentials', $gateway->getDecryptedCredentials());
        });

        return view('admin.payment-gateways.index', compact('gateways'));
    }

    /**
     * Toggle gateway active status.
     */
    public function toggle(Request $request, PaymentGateway $gateway)
    {
        $gateway->update(['is_active' => ! $gateway->is_active]);

        return back()->with('success', $gateway->name . ' has been ' . ($gateway->is_active ? 'activated' : 'deactivated') . '.');
    }

    /**
     * Save gateway credentials and sandbox mode.
     */
    public function configure(Request $request, PaymentGateway $gateway)
    {
        $credentials = $request->input('credentials', []);
        $isSandbox   = $request->boolean('is_sandbox', true);

        // Filter out empty credentials (keep existing if not provided)
        $existing = $gateway->getDecryptedCredentials();
        foreach ($credentials as $key => $value) {
            if ($value === '' || $value === null) {
                $credentials[$key] = $existing[$key] ?? '';
            }
        }

        $gateway->setEncryptedCredentials($credentials);
        $gateway->update(['is_sandbox' => $isSandbox]);

        return back()->with('success', $gateway->name . ' credentials saved successfully.');
    }
}
