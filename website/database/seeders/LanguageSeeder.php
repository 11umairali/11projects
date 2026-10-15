<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['code' => 'en', 'name' => 'English',    'native_name' => 'English',   'direction' => 'ltr', 'flag' => "\u{1F1FA}\u{1F1F8}", 'is_active' => true,  'is_default' => true,  'sort_order' => 1],
            ['code' => 'es', 'name' => 'Spanish',    'native_name' => 'Español',   'direction' => 'ltr', 'flag' => "\u{1F1EA}\u{1F1F8}", 'is_active' => true,  'is_default' => false, 'sort_order' => 2],
            ['code' => 'fr', 'name' => 'French',     'native_name' => 'Français',  'direction' => 'ltr', 'flag' => "\u{1F1EB}\u{1F1F7}", 'is_active' => true,  'is_default' => false, 'sort_order' => 3],
            ['code' => 'de', 'name' => 'German',     'native_name' => 'Deutsch',   'direction' => 'ltr', 'flag' => "\u{1F1E9}\u{1F1EA}", 'is_active' => true,  'is_default' => false, 'sort_order' => 4],
            ['code' => 'pt', 'name' => 'Portuguese', 'native_name' => 'Português', 'direction' => 'ltr', 'flag' => "\u{1F1E7}\u{1F1F7}", 'is_active' => true,  'is_default' => false, 'sort_order' => 5],
            ['code' => 'ar', 'name' => 'Arabic',     'native_name' => 'العربية',     'direction' => 'rtl', 'flag' => "\u{1F1F8}\u{1F1E6}", 'is_active' => true,  'is_default' => false, 'sort_order' => 6],
            ['code' => 'hi', 'name' => 'Hindi',      'native_name' => 'हिन्दी',      'direction' => 'ltr', 'flag' => "\u{1F1EE}\u{1F1F3}", 'is_active' => true,  'is_default' => false, 'sort_order' => 7],
            ['code' => 'zh', 'name' => 'Chinese',    'native_name' => '中文',        'direction' => 'ltr', 'flag' => "\u{1F1E8}\u{1F1F3}", 'is_active' => false, 'is_default' => false, 'sort_order' => 8],
            ['code' => 'ja', 'name' => 'Japanese',   'native_name' => '日本語',       'direction' => 'ltr', 'flag' => "\u{1F1EF}\u{1F1F5}", 'is_active' => false, 'is_default' => false, 'sort_order' => 9],
            ['code' => 'ko', 'name' => 'Korean',     'native_name' => '한국어',       'direction' => 'ltr', 'flag' => "\u{1F1F0}\u{1F1F7}", 'is_active' => false, 'is_default' => false, 'sort_order' => 10],
            ['code' => 'tr', 'name' => 'Turkish',    'native_name' => 'Türkçe',    'direction' => 'ltr', 'flag' => "\u{1F1F9}\u{1F1F7}", 'is_active' => true,  'is_default' => false, 'sort_order' => 11],
            ['code' => 'ru', 'name' => 'Russian',    'native_name' => 'Русский',   'direction' => 'ltr', 'flag' => "\u{1F1F7}\u{1F1FA}", 'is_active' => false, 'is_default' => false, 'sort_order' => 12],
            ['code' => 'it', 'name' => 'Italian',    'native_name' => 'Italiano',  'direction' => 'ltr', 'flag' => "\u{1F1EE}\u{1F1F9}", 'is_active' => true,  'is_default' => false, 'sort_order' => 13],
            ['code' => 'nl', 'name' => 'Dutch',      'native_name' => 'Nederlands','direction' => 'ltr', 'flag' => "\u{1F1F3}\u{1F1F1}", 'is_active' => false, 'is_default' => false, 'sort_order' => 14],
            ['code' => 'bn', 'name' => 'Bengali',    'native_name' => 'বাংলা',      'direction' => 'ltr', 'flag' => "\u{1F1E7}\u{1F1E9}", 'is_active' => true,  'is_default' => false, 'sort_order' => 15],
            ['code' => 'id', 'name' => 'Indonesian', 'native_name' => 'Bahasa',    'direction' => 'ltr', 'flag' => "\u{1F1EE}\u{1F1E9}", 'is_active' => false, 'is_default' => false, 'sort_order' => 16],
            ['code' => 'he', 'name' => 'Hebrew',     'native_name' => 'עברית',      'direction' => 'rtl', 'flag' => "\u{1F1EE}\u{1F1F1}", 'is_active' => false, 'is_default' => false, 'sort_order' => 17],
            ['code' => 'ur', 'name' => 'Urdu',       'native_name' => 'اردو',       'direction' => 'rtl', 'flag' => "\u{1F1F5}\u{1F1F0}", 'is_active' => false, 'is_default' => false, 'sort_order' => 18],
        ];

        foreach ($languages as $lang) {
            DB::table('languages')->updateOrInsert(
                ['code' => $lang['code']],
                array_merge($lang, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ]),
            );
        }
    }
}
