<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('market_analyses', function (Blueprint $table): void {
            $table->string('publication_status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->index(['publication_status', 'published_at', 'id'], 'market_outlook_publication_idx');
        });

        // A successful Discord delivery was the only prior publication signal.
        DB::table('market_analyses')->where('discord_sent', true)->update([
            'publication_status' => 'published',
            'published_at' => DB::raw('COALESCE((SELECT MIN(d.created_at) FROM market_outlook_discord d WHERE d.outlook_id = market_analyses.id), market_analyses.created_at, market_analyses.updated_at)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('market_analyses', function (Blueprint $table): void {
            $table->dropIndex('market_outlook_publication_idx');
            $table->dropColumn(['publication_status', 'published_at']);
        });
    }
};
