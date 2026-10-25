<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_ticket_activity', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_name', 255)->nullable();
            $table->string('event_type', 40);
            $table->string('field_name', 64)->nullable();
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'created_at', 'id']);
            $table->index(['issue_id', 'event_type', 'created_at']);
        });
        Schema::table('pm_ticket_links', function (Blueprint $table): void {
            $table->index(['target_issue_id', 'relation']);
        });
    }

    public function down(): void
    {
        Schema::table('pm_ticket_links', fn (Blueprint $table) => $table->dropIndex(['target_issue_id', 'relation']));
        Schema::dropIfExists('pm_ticket_activity');
    }
};
