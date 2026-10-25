<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trading_blogs', function (Blueprint $table): void {
            $table->string('source', 30)->default('WEBSITE')->after('author_id');
            $table->string('client_request_id', 120)->nullable()->after('source');
            $table->unsignedInteger('version')->default(0)->after('views');
            $table->unique(['author_id', 'client_request_id'], 'trading_blogs_author_request_unique');
            $table->index(['status', 'published_at'], 'trading_blogs_publication_index');
        });
    }

    public function down(): void
    {
        Schema::table('trading_blogs', function (Blueprint $table): void {
            $table->dropUnique('trading_blogs_author_request_unique');
            $table->dropIndex('trading_blogs_publication_index');
            $table->dropColumn([
                'source',
                'client_request_id',
                'version',
            ]);
        });
    }
};
