<?php

namespace App\Console\Commands;

use App\Support\TradingAiSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneTradingAiData extends Command
{
    protected $signature = 'trading-ai:prune';
    protected $description = 'Delete expired TradingHub AI conversations and usage metadata';

    public function handle(): int
    {
        if (! Schema::hasTable('ai_chat_conversations')) return self::SUCCESS;
        $cutoff = now()->subDays(TradingAiSettings::get()['retention_days']);
        $deleted = DB::table('ai_chat_conversations')->where('updated_at', '<', $cutoff)->delete();
        DB::table('ai_chat_usage')->where('created_at', '<', $cutoff)->delete();
        $this->info("Pruned {$deleted} expired conversations.");
        return self::SUCCESS;
    }
}
