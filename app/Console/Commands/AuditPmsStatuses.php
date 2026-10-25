<?php

namespace App\Console\Commands;

use App\Support\PmsStatusRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditPmsStatuses extends Command
{
    protected $signature = 'pms:audit-statuses {--strict : Return an error when unmapped or mismatched values exist}';
    protected $description = 'Show PMS legacy status values, integer mappings, and unmapped records';

    public function handle(): int
    {
        $problems = 0;
        foreach (PmsStatusRegistry::TABLE_DOMAINS as $table => $domain) {
            if (! Schema::hasTable($table)) continue;
            $hasCodes = Schema::hasColumn($table, 'status_code');
            if (! $hasCodes) $this->warn("{$table}: status_code migration pending; inventory only.");
            $columns = ['status', DB::raw('COUNT(*) as records')];
            if ($hasCodes) $columns[] = 'status_code';
            $rows = DB::table($table)->select($columns)->groupBy($hasCodes ? ['status', 'status_code'] : ['status'])
                ->orderBy('status')->get();
            $this->line("{$table} ({$domain})");
            foreach ($rows as $row) {
                $expected = PmsStatusRegistry::code($domain, $row->status);
                $stored = $hasCodes ? $row->status_code : null;
                $bad = $expected === null || ($hasCodes && (int) $stored !== $expected);
                if ($bad) $problems += $row->records;
                $this->line(sprintf('  %s: %d row(s), code %s, expected %s%s',
                    json_encode($row->status), $row->records, $stored ?? 'NULL', $expected ?? 'unmapped', $bad ? '  REVIEW' : ''));
            }
        }

        $this->line("Records needing review: {$problems}");
        return $this->option('strict') && $problems ? self::FAILURE : self::SUCCESS;
    }
}
