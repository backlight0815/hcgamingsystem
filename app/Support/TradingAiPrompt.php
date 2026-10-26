<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TradingAiPrompt
{
    public static function current(): array
    {
        $row = Schema::hasTable('ai_chat_prompts')
            ? DB::table('ai_chat_prompts')->where('status', 'published')
                ->whereNotNull('approved_at')->orderByDesc('version')->first()
            : null;
        return $row
            ? ['version' => (int) $row->version, 'content' => $row->content]
            : ['version' => 3, 'content' => trim((string) file_get_contents(database_path('seeders/data/trading_ai_master_prompt_v3.txt')))];
    }
}
