<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PlanSeeder::class,
            PermissionsSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
            AdminSeeder::class,
            DemoUserSeeder::class,
            PaymentGatewaySeeder::class,
            EmailTemplateSeeder::class,
            EmailTemplateCatalogSeeder::class,
            EmailTemplatePremiumSeeder::class,
            EmailTemplatePremiumSeeder2::class,
            EmailTemplateEnterprise::class,
            HelpArticleSeeder::class,
            TestimonialSeeder::class,
            PageSeeder::class,
            CommercialDemoSeeder::class,
            LanguageSeeder::class,
            CurrencySeeder::class,
            PaymentGatewayCategorySeeder::class,
        ]);
    }
}
