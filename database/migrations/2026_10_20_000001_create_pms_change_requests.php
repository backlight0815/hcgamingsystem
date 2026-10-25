<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('subject_type', 16);
            $table->unsignedBigInteger('subject_id');
            $table->foreignId('project_id')->constrained('pm_projects');
            $table->string('request_type', 16);
            $table->unsignedInteger('revision');
            $table->string('source', 16);
            $table->unsignedInteger('base_lock_version');
            $table->json('original_snapshot');
            $table->json('proposed_changes')->nullable();
            $table->unsignedTinyInteger('status_code')->default(1);
            $table->foreignId('submitted_by')->constrained('users');
            $table->foreignId('reviewer_id')->constrained('users');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id', 'status_code']);
            $table->index(['project_id', 'reviewer_id', 'status_code']);
            $table->index(['submitted_by', 'status_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_change_requests');
    }
};
