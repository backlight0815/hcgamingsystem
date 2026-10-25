<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_priorities', function (Blueprint $table) {
            $table->string('code', 2)->primary();
            $table->string('label');
            $table->unsignedTinyInteger('rank')->unique();
            $table->boolean('active')->default(true);
        });
        DB::table('pm_priorities')->insert([
            ['code' => 'P0', 'label' => 'Blocker', 'rank' => 0],
            ['code' => 'P1', 'label' => 'Critical', 'rank' => 1],
            ['code' => 'P2', 'label' => 'High', 'rank' => 2],
            ['code' => 'P3', 'label' => 'Medium', 'rank' => 3],
            ['code' => 'P4', 'label' => 'Low', 'rank' => 4],
        ]);

        Schema::create('pm_projects', function (Blueprint $table) {
            $table->id();
            $table->string('key', 12)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('objective')->nullable();
            $table->string('category')->nullable();
            $table->string('status', 24)->default('Planned')->index();
            $table->string('priority', 2)->default('P3');
            $table->foreign('priority')->references('code')->on('pm_priorities');
            $table->foreignId('owner_id')->constrained('users');
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('related_module', 40)->nullable();
            $table->date('planned_start_date')->nullable();
            $table->date('target_date')->nullable()->index();
            $table->date('completed_at')->nullable();
            $table->unsignedInteger('next_issue_number')->default(1);
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('pm_project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32);
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'role']);
        });

        Schema::create('pm_work_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->string('issue_key', 32)->unique();
            $table->string('type', 32)->default('Task');
            $table->string('summary');
            $table->longText('description')->nullable();
            $table->longText('acceptance_criteria')->nullable();
            $table->string('status', 40)->default('Planned')->index();
            $table->string('priority', 2)->default('P3');
            $table->foreign('priority')->references('code')->on('pm_priorities');
            $table->string('severity', 20)->nullable();
            $table->foreignId('reporter_id')->constrained('users');
            $table->foreignId('developer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('qa_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->date('due_date')->nullable()->index();
            $table->unsignedInteger('estimate_minutes')->nullable();
            $table->unsignedInteger('time_spent_minutes')->default(0);
            $table->text('dependency_reason')->nullable();
            $table->foreignId('dependency_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('development_started_at')->nullable();
            $table->timestamp('qa_completed_at')->nullable();
            $table->string('qat_build')->nullable();
            $table->timestamp('qat_deployed_at')->nullable();
            $table->text('qat_deployment_evidence')->nullable();
            $table->text('qa_result')->nullable();
            $table->string('production_build')->nullable();
            $table->timestamp('production_deployed_at')->nullable();
            $table->text('production_evidence')->nullable();
            $table->foreignId('production_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('reopen_count')->default(0);
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['project_id', 'status', 'priority']);
            $table->index(['developer_id', 'status']);
            $table->index(['qa_id', 'status']);
        });

        Schema::create('pm_issue_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event', 40);
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->text('reason')->nullable();
            $table->json('changes')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['issue_id', 'created_at']);
        });

        Schema::create('pm_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('action', 80);
            $table->string('subject_type', 32);
            $table->unsignedBigInteger('subject_id');
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['subject_type', 'subject_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_audit_logs');
        Schema::dropIfExists('pm_issue_history');
        Schema::dropIfExists('pm_work_items');
        Schema::dropIfExists('pm_project_members');
        Schema::dropIfExists('pm_projects');
        Schema::dropIfExists('pm_priorities');
    }
};
