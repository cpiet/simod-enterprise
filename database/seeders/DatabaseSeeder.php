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
            CommandCenterDynamicSeeder::class,   // koordinat Polres (+ data contoh bila SIMOD_SEED_SAMPLE=true)
            SocialIntelligenceSeeder::class,     // pengaturan medsos (+ postingan contoh bila SIMOD_SEED_SAMPLE=true)
        ]);

        if (config('simod.seed_sample_data')) {
            $this->call(MultiPoldaArticlesHistoricalSeeder::class);   // berita historis contoh
        }

        // Ensure 4 Pillars & Dynamic Menu Navigation are seeded
        MenuItem::ensureDefaultMenusSeeded();
    }
}
