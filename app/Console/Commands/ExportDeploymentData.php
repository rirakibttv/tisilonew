<?php

namespace App\Console\Commands;

use App\Services\DeploymentDataSnapshot;
use Illuminate\Console\Command;

class ExportDeploymentData extends Command
{
    protected $signature = 'deployment-data:export {--path= : Custom snapshot path}';

    protected $description = 'Export safe catalog and reference data for Git-backed production deployment';

    public function handle(DeploymentDataSnapshot $snapshot): int
    {
        $path = $snapshot->export($this->option('path') ?: null);
        $this->components->info('Deployable catalog snapshot updated: '.$path);

        return self::SUCCESS;
    }
}
