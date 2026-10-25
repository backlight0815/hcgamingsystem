<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_wiki_pages', function (Blueprint $table): void {
            $table->string('access_scope', 16)->default('project')->after('project_id');
            $table->index(['access_scope', 'project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('pm_wiki_pages', function (Blueprint $table): void {
            $table->dropIndex(['access_scope', 'project_id', 'status']);
            $table->dropColumn('access_scope');
        });
    }
};
