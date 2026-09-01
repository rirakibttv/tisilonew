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
    }
}
