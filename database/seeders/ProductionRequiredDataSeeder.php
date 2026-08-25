<?php

namespace Database\Seeders;

use App\Services\DeploymentDataSnapshot;
use Illuminate\Database\Seeder;

class ProductionRequiredDataSeeder extends Seeder
{
    /**
     * Merge the Git-versioned, non-sensitive catalog snapshot after migrations.
     * Users, credentials, orders, sessions, purchase prices and live inventory
     * are deliberately excluded from the snapshot.
     */
    public function run(): void
    {
        app(DeploymentDataSnapshot::class)->import();
    }
}
