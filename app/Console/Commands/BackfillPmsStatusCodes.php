<?php

namespace App\Console\Commands;

use App\Support\PmsStatusRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BackfillPmsStatusCodes extends Command
{
    protected $signature = 'pms:backfill-status-codes {--apply : Write recognized values to rows with a null code}';
    protected $description = 'Preview or repair recognized PMS statuses left without a code during a staged deployment';

    public function handle(): int
    {
        $total = 0;
        foreach (PmsStatusRegistry::TABLE_DOMAINS as $table => $domain) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'status_code')) {
                $this->warn("{$table}: status_code migration pending; skipped.");
                continue;
            }
            foreach (PmsStatusRegistry::definitions($domain) as $code => $key) {
                $query = DB::table($table)->whereNull('status_code')
                    ->whereRaw('LOWER(TRIM(status)) = ?', [mb_strtolower($key)]);
                $count = (clone $query)->count();
                if (! $count) continue;
                $this->line("{$table}: {$count} {$key} row(s) -> {$code}");
                if ($this->option('apply')) $query->update(['status_code' => $code]);
                $total += $count;
            }
        }
        $this->info(($this->option('apply') ? 'Updated' : 'Would update')." {$total} row(s). Run pms:audit-statuses --strict afterward.");
        return self::SUCCESS;
    }
}
