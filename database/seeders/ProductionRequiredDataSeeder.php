<?php

namespace Database\Seeders;

use App\Services\DeploymentDataSnapshot;
use Illuminate\Database\Seeder;

class ProductionRequiredDataSeeder extends Seeder
{
    /**
     * Merge Git-versioned catalog, Add User, vendor master data, inventory
     * baselines and non-secret settings after migrations. Session/reset tokens,
     * analytics/audit events and encrypted settings secrets remain production-local.
     */
    public function run(): void
    {
        $this->call(GeneralSettingsSeeder::class);
        app(DeploymentDataSnapshot::class)->import();
    }
}
