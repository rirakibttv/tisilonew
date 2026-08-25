<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionRequiredDataSeeder extends Seeder
{
    /**
     * Seed only application-owned reference data required in every environment.
     *
     * Every write added here must be idempotent (for example, updateOrInsert or
     * updateOrCreate). Customer accounts, administrators, products, inventory,
     * orders, and other production-owned records must never be seeded here.
     */
    public function run(): void
    {
        // No shared reference records are required yet. The deployment pipeline
        // executes this seeder after every successful database migration.
    }
}
