<?php

namespace Database\Seeders;

use App\Services\DeploymentDataSnapshot;
use Illuminate\Database\Seeder;

class ProductionRequiredDataSeeder extends Seeder
{
    /**
     * Merge Git-versioned catalog, non-credential user profiles, vendor master
     * data, inventory baselines and non-secret settings after migrations.
     * Passwords, phone numbers, sessions, analytics and encrypted secrets remain
     * production-local. New synced users must set a password through reset flow.
     */
    public function run(): void
    {
        $this->call(GeneralSettingsSeeder::class);
        app(DeploymentDataSnapshot::class)->import();
        $this->call(LandingPageTemplateSeeder::class);
    }
}
