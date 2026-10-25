<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_wiki_pages', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('space_id')->constrained('pm_projects')->nullOnDelete();
            $table->string('category', 80)->nullable()->after('title');
            $table->string('audience', 255)->nullable()->after('category');
            $table->text('summary')->nullable()->after('audience');
            $table->string('system_key', 80)->nullable()->unique()->after('summary');
            $table->index(['project_id', 'status', 'updated_at']);
            $table->index(['category', 'status']);
        });

        Schema::table('pm_wiki_revisions', function (Blueprint $table): void {
            $table->unsignedBigInteger('project_id')->nullable()->after('title');
            $table->string('category', 80)->nullable()->after('project_id');
            $table->string('audience', 255)->nullable()->after('category');
            $table->text('summary')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('pm_wiki_revisions', function (Blueprint $table): void {
            $table->dropColumn(['project_id', 'category', 'audience', 'summary']);
        });
        Schema::table('pm_wiki_pages', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'status', 'updated_at']);
            $table->dropIndex(['category', 'status']);
            $table->dropUnique(['system_key']);
            $table->dropForeign(['project_id']);
            $table->dropColumn(['project_id', 'category', 'audience', 'summary', 'system_key']);
        });
    }
};
