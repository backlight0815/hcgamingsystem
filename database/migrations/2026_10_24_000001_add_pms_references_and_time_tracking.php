<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pm_project_reference_numbers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id')->nullable()->unique();
            $table->dateTime('created_at');
        });
        Schema::table('pm_projects', function (Blueprint $table) {
            $table->string('project_reference', 24)->nullable()->unique();
        });
        // Include soft-deleted projects, so their public references cannot be reused.
        DB::table('pm_projects')->orderBy('id')->select('id')->chunkById(200, function ($projects): void {
            foreach ($projects as $project) {
                $number = DB::table('pm_project_reference_numbers')->insertGetId([
                    'project_id' => $project->id, 'created_at' => now(),
                ]);
                DB::table('pm_projects')->where('id', $project->id)->update(['project_reference' => 'PJ'.$number]);
            }
        });

        Schema::create('pm_time_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('project_id')->constrained('pm_projects');
            $table->foreignId('ticket_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->string('work_type', 32);
            $table->text('description');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedInteger('duration_seconds');
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['user_id', 'ended_at']);
            $table->index(['project_id', 'ended_at']);
        });
        Schema::create('pm_work_timers', function (Blueprint $table) {
            $table->foreignId('user_id')->primary()->constrained('users');
            $table->foreignId('project_id')->constrained('pm_projects');
            $table->foreignId('ticket_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->string('work_type', 32);
            $table->text('description');
            $table->string('status', 16);
            $table->dateTime('started_at');
            $table->dateTime('resumed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
        });
        Schema::create('pm_workspace_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_hash', 64)->unique();
            $table->string('activity_nonce', 48);
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('started_at');
            $table->dateTime('last_eligible_at');
            $table->dateTime('ended_at')->nullable();
            $table->string('end_reason', 32)->nullable();
            $table->unsignedInteger('active_seconds')->default(0);
            $table->dateTime('last_workspace_at')->nullable();
            $table->dateTime('created_at');
            $table->dateTime('updated_at');
            $table->index(['user_id', 'started_at']);
        });
        Schema::create('pm_workspace_activity_intervals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('pm_workspace_sessions');
            $table->foreignId('user_id')->constrained('users');
            $table->dateTime('started_at');
            $table->dateTime('ended_at');
            $table->unsignedSmallInteger('duration_seconds');
            $table->index(['user_id', 'ended_at']);
        });
        Schema::create('pm_time_entry_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('pm_time_entries');
            $table->foreignId('actor_id')->constrained('users');
            $table->unsignedInteger('old_duration_seconds');
            $table->unsignedInteger('new_duration_seconds');
            $table->text('old_description');
            $table->text('new_description');
            $table->text('reason');
            $table->dateTime('created_at');
            $table->index(['entry_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_time_entry_adjustments');
        Schema::dropIfExists('pm_workspace_activity_intervals');
        Schema::dropIfExists('pm_workspace_sessions');
        Schema::dropIfExists('pm_work_timers');
        Schema::dropIfExists('pm_time_entries');
        Schema::table('pm_projects', fn (Blueprint $table) => $table->dropColumn('project_reference'));
        Schema::dropIfExists('pm_project_reference_numbers');
    }
};
