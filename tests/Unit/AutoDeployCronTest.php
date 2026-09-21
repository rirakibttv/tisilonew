<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AutoDeployCronTest extends TestCase
{
    public function test_production_deploy_script_enforces_a_safe_one_minute_cron(): void
    {
        $script = File::get(base_path('scripts/deploy-production.sh'));

        $this->assertStringContainsString(
            '* * * * * /usr/bin/env bash ${REPOSITORY}/scripts/deploy-production.sh',
            $script,
        );
        $this->assertStringContainsString('crontab "${temporary_crontab}"', $script);
        $this->assertStringContainsString('flock -n 9', $script);
        $this->assertStringContainsString('ensure_minute_auto_deploy_cron', $script);
        $this->assertStringContainsString('artisan filament:optimize-clear', $script);
        $this->assertStringNotContainsString('artisan filament:optimize\n', $script);
        $this->assertStringContainsString('readonly BACKUP_ROOT="/home/rirakib/TisiloBackup"', $script);
        $this->assertStringContainsString('bootstrap-storage.tar.gz', $script);
        $this->assertStringContainsString('target_has_tracked_production_files', $script);
        $this->assertStringContainsString('restore_production_files', $script);
        $this->assertStringContainsString('prepare_public_storage_link', $script);
        $this->assertStringContainsString('scripts/cpanel-index.php', $script);
        $this->assertStringNotContainsString('ProductionRequiredDataSeeder', $script);
        $this->assertStringNotContainsString('deployable-catalog:import', $script);
        $this->assertStringNotContainsString('db:seed', $script);
    }

    public function test_filament_component_cache_uses_the_writable_storage_directory(): void
    {
        $this->assertSame(
            storage_path('framework/cache/filament'),
            config('filament.cache_path'),
        );
    }
}
