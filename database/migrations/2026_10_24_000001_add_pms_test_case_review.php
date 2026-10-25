<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pm_ticket_attachments', function (Blueprint $table): void {
            $table->string('category', 32)->default('evidence')->index();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('replaces_attachment_id')->nullable()->constrained('pm_ticket_attachments')->nullOnDelete();
            $table->timestamp('removed_at')->nullable()->index();
            $table->foreignId('removed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index(['issue_id', 'category', 'created_at'], 'pm_attachment_case_history_idx');
        });

        Schema::create('pm_test_case_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->unique()->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 24)->default('not_requested');
            $table->unsignedInteger('submission_version')->default(0);
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['reviewer_id', 'status']);
        });

        Schema::create('pm_test_case_review_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('review_id')->constrained('pm_test_case_reviews')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->string('previous_status', 24)->nullable();
            $table->string('new_status', 24);
            $table->unsignedInteger('submission_version');
            $table->text('comment')->nullable();
            $table->timestamp('created_at');
            $table->index(['review_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_test_case_review_events');
        Schema::dropIfExists('pm_test_case_reviews');
        Schema::table('pm_ticket_attachments', function (Blueprint $table): void {
            $table->dropIndex('pm_attachment_case_history_idx');
            $table->dropConstrainedForeignId('removed_by');
            $table->dropConstrainedForeignId('replaces_attachment_id');
            $table->dropColumn(['category', 'version', 'removed_at']);
        });
    }
};
