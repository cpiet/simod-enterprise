<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            EnterpriseDatabaseSeeder::class,
            MultiTenantPoldasSeeder::class,
            DivhumasNationalRssFeedsSeeder::class,
            MultiPoldaArticlesHistoricalSeeder::class,
            CommandCenterDynamicSeeder::class,
            SocialIntelligenceSeeder::class,
        ]);

        // Ensure 4 Pillars & Dynamic Menu Navigation are seeded
        MenuItem::ensureDefaultMenusSeeded();
    }
}
