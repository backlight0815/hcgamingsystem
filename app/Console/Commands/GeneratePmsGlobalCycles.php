<?php

namespace App\Console\Commands;

use App\Services\PmsGlobalCycleService;
use Illuminate\Console\Command;

class GeneratePmsGlobalCycles extends Command
{
    protected $signature = 'pms:generate-cycles';

    protected $description = 'Generate due organization development cycles and review expired cycles';

    public function handle(PmsGlobalCycleService $cycles): int
    {
        if (! module_enabled('project_management')) {
            return self::SUCCESS;
        }
        $count = $cycles->generateDue();
        $this->info("Generated {$count} organization cycle(s).");

        return self::SUCCESS;
    }
}
