<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_ticket_comments', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('issue_id')->constrained('pm_ticket_comments')->nullOnDelete();
            $table->index(['issue_id', 'parent_id', 'created_at'], 'pm_comments_thread_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pm_ticket_comments', function (Blueprint $table): void {
            $table->dropIndex('pm_comments_thread_idx');
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
