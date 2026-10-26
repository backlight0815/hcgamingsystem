<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TradingAiMasterPromptSeeder extends Seeder
{
    public function run(): void
    {
        $content = trim((string) file_get_contents(__DIR__.'/data/trading_ai_master_prompt_v3.txt'));
        DB::table('ai_chat_prompts')->insertOrIgnore([
            'version' => 3, 'content' => $content, 'status' => 'published',
            'approved_at' => now(), 'published_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
