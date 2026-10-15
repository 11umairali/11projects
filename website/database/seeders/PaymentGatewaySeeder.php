<?php

namespace Database\Seeders;

use App\Models\PaymentGateway;
use Illuminate\Database\Seeder;

class PaymentGatewaySeeder extends Seeder
{
    public function run(): void
    {
        $gateways = [
            // --- Top 10 Traditional Gateways ---
            ['name' => 'Stripe',         'slug' => 'stripe',         'description' => 'Accept credit/debit cards via Stripe Checkout.',                    'supported_currencies' => ['USD','EUR','GBP','INR','AUD','CAD','SGD','JPY','BRL','MXN','CHF'], 'sort_order' => 1],
            ['name' => 'PayPal',         'slug' => 'paypal',         'description' => 'Accept payments via PayPal Checkout.',                              'supported_currencies' => ['USD','EUR','GBP','AUD','CAD','JPY','BRL','MXN','CHF','HKD'], 'sort_order' => 2],
            ['name' => 'Razorpay',       'slug' => 'razorpay',       'description' => 'Accept payments in India via Razorpay.',                            'supported_currencies' => ['INR','USD','EUR','GBP','SGD'], 'sort_order' => 3],
            ['name' => 'Mollie',         'slug' => 'mollie',         'description' => 'European payment gateway supporting iDEAL, Bancontact, cards.',     'supported_currencies' => ['EUR','USD','GBP','CHF','SEK','NOK','DKK'], 'sort_order' => 4],
            ['name' => 'Paystack',       'slug' => 'paystack',       'description' => 'Accept payments in Africa via Paystack.',                           'supported_currencies' => ['NGN','GHS','ZAR','USD'], 'sort_order' => 5],
            ['name' => 'Flutterwave',    'slug' => 'flutterwave',    'description' => 'Accept payments across Africa via Flutterwave (Rave).',             'supported_currencies' => ['NGN','GHS','KES','ZAR','USD','EUR','GBP'], 'sort_order' => 6],
            ['name' => 'Paytm',          'slug' => 'paytm',          'description' => 'Accept payments in India via Paytm Business.',                      'supported_currencies' => ['INR'], 'sort_order' => 7],
            ['name' => 'Square',         'slug' => 'square',         'description' => 'Accept card payments via Square Checkout.',                         'supported_currencies' => ['USD','CAD','AUD','GBP','EUR','JPY'], 'sort_order' => 8],
            ['name' => 'Braintree',      'slug' => 'braintree',      'description' => 'Accept card payments via Braintree (PayPal).',                      'supported_currencies' => ['USD','EUR','GBP','AUD','CAD'], 'sort_order' => 9],
            ['name' => '2Checkout',      'slug' => 'twocheckout',    'description' => 'Accept payments via 2Checkout (Verifone).',                         'supported_currencies' => ['USD','EUR','GBP','BRL','ARS','MXN'], 'sort_order' => 10],

            // --- Additional 18 Traditional Gateways ---
            ['name' => 'Coinbase (Legacy)',     'slug' => 'coinbase',       'description' => 'Accept crypto payments via Coinbase Commerce (legacy driver).', 'supported_currencies' => ['USD','EUR','GBP','BTC','ETH'], 'sort_order' => 11],
            ['name' => 'Mercado Pago',   'slug' => 'mercadopago',    'description' => 'Accept payments in Latin America via Mercado Pago.',                'supported_currencies' => ['BRL','ARS','MXN','CLP','COP','PEN','UYU'], 'sort_order' => 12],
            ['name' => 'iyzico',         'slug' => 'iyzico',         'description' => 'Accept payments in Turkey via iyzico.',                             'supported_currencies' => ['TRY','EUR','USD','GBP'], 'sort_order' => 13],
            ['name' => 'Paddle',         'slug' => 'paddle',         'description' => 'Accept payments via Paddle (MoR).',                                 'supported_currencies' => ['USD','EUR','GBP','AUD','CAD'], 'sort_order' => 14],
            ['name' => 'Authorize.Net',  'slug' => 'authorize_net',  'description' => 'Accept card payments via Authorize.Net.',                           'supported_currencies' => ['USD','CAD','GBP','EUR','AUD'], 'sort_order' => 15],
            ['name' => 'SSLCommerz',     'slug' => 'sslcommerz',     'description' => 'Accept payments in Bangladesh via SSLCommerz.',                      'supported_currencies' => ['BDT'], 'sort_order' => 16],
            ['name' => 'Instamojo',      'slug' => 'instamojo',      'description' => 'Accept payments in India via Instamojo.',                           'supported_currencies' => ['INR'], 'sort_order' => 17],
            ['name' => 'PhonePe',        'slug' => 'phonepe',        'description' => 'Accept UPI/card payments in India via PhonePe.',                    'supported_currencies' => ['INR'], 'sort_order' => 18],
            ['name' => 'Cashfree',       'slug' => 'cashfree',       'description' => 'Accept payments in India via Cashfree.',                            'supported_currencies' => ['INR'], 'sort_order' => 19],
            ['name' => 'PayU',           'slug' => 'payu',           'description' => 'Accept payments in India/LatAm via PayU.',                          'supported_currencies' => ['INR','BRL','MXN','ARS','CLP','COP'], 'sort_order' => 20],
            ['name' => 'Midtrans',       'slug' => 'midtrans',       'description' => 'Accept payments in Indonesia via Midtrans.',                        'supported_currencies' => ['IDR'], 'sort_order' => 21],
            ['name' => 'Xendit',         'slug' => 'xendit',         'description' => 'Accept payments in Southeast Asia via Xendit.',                     'supported_currencies' => ['IDR','PHP','THB','VND','MYR'], 'sort_order' => 22],
            ['name' => 'Tap Payments',   'slug' => 'tap',            'description' => 'Accept payments in Middle East via Tap.',                           'supported_currencies' => ['KWD','BHD','SAR','AED','QAR','OMR','EGP','JOD'], 'sort_order' => 23],
            ['name' => 'HyperPay',       'slug' => 'hyperpay',       'description' => 'Accept payments in MENA via HyperPay (ACI).',                      'supported_currencies' => ['SAR','AED','BHD','EGP','JOD','KWD','OMR','QAR'], 'sort_order' => 24],
            ['name' => 'PayTR',          'slug' => 'paytr',          'description' => 'Accept payments in Turkey via PayTR.',                              'supported_currencies' => ['TRY','USD','EUR','GBP'], 'sort_order' => 25],
            ['name' => 'Fondy',          'slug' => 'fondy',          'description' => 'Accept payments in Eastern Europe via Fondy (CloudIPSP).',          'supported_currencies' => ['UAH','USD','EUR','GBP','RUB'], 'sort_order' => 26],
            ['name' => 'Skrill',         'slug' => 'skrill',         'description' => 'Accept payments via Skrill (Moneybookers).',                        'supported_currencies' => ['USD','EUR','GBP','CHF','CAD','AUD'], 'sort_order' => 27],
            ['name' => 'CinetPay',       'slug' => 'cinetpay',       'description' => 'Accept mobile money payments in West Africa via CinetPay.',         'supported_currencies' => ['XOF','XAF','GNF','CDF'], 'sort_order' => 28],

            // --- Manual / Offline ---
            ['name' => 'Bank Transfer',  'slug' => 'bank_transfer',  'description' => 'Accept manual bank transfer payments.',                             'supported_currencies' => ['USD','EUR','GBP','INR','AUD','CAD'], 'sort_order' => 29],
            ['name' => 'Offline Payment','slug' => 'offline',        'description' => 'Accept offline/manual payments (cash, cheque, etc.).',              'supported_currencies' => ['USD','EUR','GBP','INR'], 'sort_order' => 30],

            // --- Crypto Gateways (10) ---
            ['name' => 'Coinbase Commerce', 'slug' => 'coinbase_commerce', 'description' => 'Accept crypto payments via Coinbase Commerce (BTC, ETH, LTC, etc.).', 'supported_currencies' => ['USD','EUR','GBP','BTC','ETH','LTC','USDC','DAI'], 'sort_order' => 31],
            ['name' => 'CoinPayments',   'slug' => 'coinpayments',   'description' => 'Accept 2000+ cryptocurrencies via CoinPayments.',                  'supported_currencies' => ['USD','EUR','GBP','BTC','ETH','LTC','XRP','DOGE','USDT'], 'sort_order' => 32],
            ['name' => 'NOWPayments',    'slug' => 'nowpayments',    'description' => 'Accept 150+ cryptocurrencies via NOWPayments.',                     'supported_currencies' => ['USD','EUR','GBP','BTC','ETH','XRP','USDT','USDC','BNB'], 'sort_order' => 33],
            ['name' => 'BitPay',         'slug' => 'bitpay',         'description' => 'Accept Bitcoin and crypto payments via BitPay.',                    'supported_currencies' => ['USD','EUR','GBP','BTC','BCH','ETH','XRP','DOGE'], 'sort_order' => 34],
            ['name' => 'Cryptomus',      'slug' => 'cryptomus',      'description' => 'Accept crypto payments via Cryptomus gateway.',                     'supported_currencies' => ['USD','EUR','BTC','ETH','USDT','TRX','BNB','LTC'], 'sort_order' => 35],
            ['name' => 'Plisio',         'slug' => 'plisio',         'description' => 'Accept crypto payments with low fees via Plisio.',                  'supported_currencies' => ['USD','EUR','BTC','ETH','LTC','DASH','DOGE','XMR','USDT'], 'sort_order' => 36],
            ['name' => 'CoinGate',       'slug' => 'coingate',       'description' => 'Accept 70+ cryptocurrencies via CoinGate.',                         'supported_currencies' => ['USD','EUR','GBP','BTC','ETH','LTC','XRP','USDT'], 'sort_order' => 37],
            ['name' => 'Blockonomics',   'slug' => 'blockonomics',   'description' => 'Accept Bitcoin payments directly to your wallet via Blockonomics.', 'supported_currencies' => ['USD','EUR','GBP','BTC'], 'sort_order' => 38],
            ['name' => 'Triple-A',       'slug' => 'triple_a',       'description' => 'Accept crypto payments with instant settlement via Triple-A.',      'supported_currencies' => ['USD','EUR','SGD','BTC','ETH','USDT','USDC','BNB'], 'sort_order' => 39],
            ['name' => 'OxaPay',         'slug' => 'oxapay',         'description' => 'Accept crypto payments with auto-conversion via OxaPay.',           'supported_currencies' => ['USD','EUR','BTC','ETH','USDT','TRX','BNB','LTC','DOGE'], 'sort_order' => 40],
        ];

        foreach ($gateways as $gateway) {
            PaymentGateway::updateOrCreate(
                ['slug' => $gateway['slug']],
                [
                    'name'                 => $gateway['name'],
                    'description'          => $gateway['description'],
                    'is_active'            => false,
                    'is_sandbox'           => true,
                    'supported_currencies' => $gateway['supported_currencies'],
                    'sort_order'           => $gateway['sort_order'],
                ],
            );
        }
    }
}
