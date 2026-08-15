<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Order matters: permissions before users, COA before settings (the account
     * mappings reference account codes), master data before demo transactions.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            ChartOfAccountSeeder::class,
            SettingSeeder::class,
            MasterDataSeeder::class,
            PricingSeeder::class,
            HrSeeder::class,
            UserSeeder::class,
        ]);

        if (app()->environment('local', 'development')) {
            $this->call(DemoTransactionSeeder::class);
        }
    }
}
