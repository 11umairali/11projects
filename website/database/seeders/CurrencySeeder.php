<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['code' => 'USD', 'name' => 'US Dollar',          'symbol' => '$',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 1.000000,    'is_active' => true,  'is_default' => true,  'sort_order' => 1],
            ['code' => 'EUR', 'name' => 'Euro',               'symbol' => '€',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 0.920000,    'is_active' => true,  'is_default' => false, 'sort_order' => 2],
            ['code' => 'GBP', 'name' => 'British Pound',      'symbol' => '£',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 0.790000,    'is_active' => true,  'is_default' => false, 'sort_order' => 3],
            ['code' => 'INR', 'name' => 'Indian Rupee',       'symbol' => '₹',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 83.500000,   'is_active' => true,  'is_default' => false, 'sort_order' => 4],
            ['code' => 'BRL', 'name' => 'Brazilian Real',     'symbol' => 'R$',   'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 4.970000,    'is_active' => true,  'is_default' => false, 'sort_order' => 5],
            ['code' => 'AED', 'name' => 'UAE Dirham',         'symbol' => 'د.إ',  'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 3.670000,    'is_active' => true,  'is_default' => false, 'sort_order' => 6],
            ['code' => 'SAR', 'name' => 'Saudi Riyal',        'symbol' => '﷼',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 3.750000,    'is_active' => true,  'is_default' => false, 'sort_order' => 7],
            ['code' => 'JPY', 'name' => 'Japanese Yen',       'symbol' => '¥',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 0, 'exchange_rate' => 150.000000,  'is_active' => false, 'is_default' => false, 'sort_order' => 8],
            ['code' => 'CNY', 'name' => 'Chinese Yuan',       'symbol' => '¥',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 7.240000,    'is_active' => false, 'is_default' => false, 'sort_order' => 9],
            ['code' => 'CAD', 'name' => 'Canadian Dollar',    'symbol' => 'C$',   'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 1.360000,    'is_active' => true,  'is_default' => false, 'sort_order' => 10],
            ['code' => 'AUD', 'name' => 'Australian Dollar',  'symbol' => 'A$',   'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 1.530000,    'is_active' => true,  'is_default' => false, 'sort_order' => 11],
            ['code' => 'MXN', 'name' => 'Mexican Peso',       'symbol' => '$',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 17.150000,   'is_active' => true,  'is_default' => false, 'sort_order' => 12],
            ['code' => 'TRY', 'name' => 'Turkish Lira',       'symbol' => '₺',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 32.000000,   'is_active' => true,  'is_default' => false, 'sort_order' => 13],
            ['code' => 'NGN', 'name' => 'Nigerian Naira',     'symbol' => '₦',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 1570.000000, 'is_active' => true,  'is_default' => false, 'sort_order' => 14],
            ['code' => 'BDT', 'name' => 'Bangladeshi Taka',   'symbol' => '৳',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 110.000000,  'is_active' => true,  'is_default' => false, 'sort_order' => 15],
            ['code' => 'IDR', 'name' => 'Indonesian Rupiah',  'symbol' => 'Rp',   'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 0, 'exchange_rate' => 15700.000000,'is_active' => true,  'is_default' => false, 'sort_order' => 16],
            ['code' => 'PKR', 'name' => 'Pakistani Rupee',    'symbol' => 'Rs',   'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 278.000000,  'is_active' => true,  'is_default' => false, 'sort_order' => 17],
            ['code' => 'ZAR', 'name' => 'South African Rand', 'symbol' => 'R',    'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 18.500000,   'is_active' => true,  'is_default' => false, 'sort_order' => 18],
            ['code' => 'PLN', 'name' => 'Polish Zloty',       'symbol' => 'zł',   'symbol_position' => 'after',  'decimal_separator' => '.', 'thousand_separator' => ' ', 'decimal_digits' => 2, 'exchange_rate' => 3.950000,    'is_active' => false, 'is_default' => false, 'sort_order' => 19],
            ['code' => 'KES', 'name' => 'Kenyan Shilling',    'symbol' => 'KSh',  'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 154.000000,  'is_active' => false, 'is_default' => false, 'sort_order' => 20],
            ['code' => 'GHS', 'name' => 'Ghanaian Cedi',      'symbol' => 'GH₵',  'symbol_position' => 'before', 'decimal_separator' => '.', 'thousand_separator' => ',', 'decimal_digits' => 2, 'exchange_rate' => 15.400000,   'is_active' => false, 'is_default' => false, 'sort_order' => 21],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                ['code' => $currency['code']],
                array_merge($currency, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
