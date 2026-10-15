<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentGatewayCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'popular'  => ['stripe', 'paypal', 'razorpay', 'mollie', 'paystack'],
            'regional' => [
                'flutterwave', 'paytm', 'square', 'braintree', 'twocheckout',
                'mercadopago', 'iyzico', 'paddle', 'authorize_net', 'sslcommerz',
                'instamojo', 'phonepe', 'cashfree', 'payu', 'midtrans',
                'xendit', 'tap', 'hyperpay', 'paytr', 'fondy', 'skrill', 'cinetpay',
            ],
            'crypto'   => [
                'coinbase', 'coinbase_commerce', 'coinpayments', 'nowpayments',
                'bitpay', 'cryptomus', 'plisio', 'coingate', 'blockonomics',
                'triple_a', 'oxapay',
            ],
            'other'    => ['bank_transfer', 'offline'],
        ];

        foreach ($categories as $category => $slugs) {
            DB::table('payment_gateways')
                ->whereIn('slug', $slugs)
                ->update(['category' => $category]);
        }
    }
}
