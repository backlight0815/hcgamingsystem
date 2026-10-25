<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pm_workspace_activity_intervals', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->string('ticket_type', 12)->nullable();
            $table->string('activity_classification', 40)->default('General PMS Activity');
            $table->string('activity_status', 16)->default('active');
            $table->date('activity_date')->nullable();
            $table->index(['user_id', 'started_at', 'activity_status'], 'pm_activity_user_start_status');
            $table->index(['activity_date', 'user_id', 'project_id'], 'pm_activity_date_user_project');
            $table->index(['project_id', 'started_at'], 'pm_activity_project_start');
            $table->index(['ticket_id', 'started_at'], 'pm_activity_ticket_start');
        });
        DB::table('pm_workspace_activity_intervals')->orderBy('id')->chunkById(500, function ($rows): void {
            foreach ($rows as $row) {
                $start = Carbon::parse($row->started_at, 'UTC');
                $end = Carbon::parse($row->ended_at, 'UTC');
                $date = $start->copy()->timezone('Asia/Kuala_Lumpur')->toDateString();
                $midnight = $start->copy()->timezone('Asia/Kuala_Lumpur')->addDay()->startOfDay()->utc();
                $firstSeconds = min((int) $row->duration_seconds, (int) $start->diffInSeconds($midnight));
                $secondSeconds = (int) $row->duration_seconds - $firstSeconds;
                if ($end->greaterThan($midnight) && $secondSeconds > 0) {
                    DB::table('pm_workspace_activity_intervals')->where('id', $row->id)->update([
                        'ended_at' => $midnight, 'duration_seconds' => $firstSeconds, 'activity_date' => $date,
                    ]);
                    DB::table('pm_workspace_activity_intervals')->insert([
                        'session_id' => $row->session_id, 'user_id' => $row->user_id,
                        'started_at' => $midnight, 'ended_at' => $end, 'duration_seconds' => $secondSeconds,
                        'activity_date' => $midnight->copy()->timezone('Asia/Kuala_Lumpur')->toDateString(),
                        'activity_classification' => 'General PMS Activity', 'activity_status' => 'active',
                    ]);
                } else {
                    DB::table('pm_workspace_activity_intervals')->where('id', $row->id)->update(['activity_date' => $date]);
                }
            }
        });
        Schema::table('pms_workspace_settings', function (Blueprint $table): void {
            $table->unsignedSmallInteger('activity_idle_seconds')->default(300);
            $table->unsignedSmallInteger('activity_retention_days')->default(365);
        });
        Schema::create('pm_workspace_tab_states', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_id')->constrained('pm_workspace_sessions')->cascadeOnDelete();
            $table->string('tab_hash', 64);
            $table->string('state', 16)->default('unfocused');
            $table->dateTime('last_seen_at');
            $table->dateTime('last_interaction_at')->nullable();
            $table->foreignId('project_id')->nullable()->constrained('pm_projects')->nullOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->string('ticket_type', 12)->nullable();
            $table->timestamps();
            $table->unique(['session_id', 'tab_hash']);
            $table->index(['session_id', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_workspace_tab_states');
        Schema::table('pms_workspace_settings', fn (Blueprint $table) => $table->dropColumn(['activity_idle_seconds', 'activity_retention_days']));
        Schema::table('pm_workspace_activity_intervals', function (Blueprint $table): void {
            $table->dropIndex('pm_activity_user_start_status');
            $table->dropIndex('pm_activity_date_user_project');
            $table->dropIndex('pm_activity_project_start');
            $table->dropIndex('pm_activity_ticket_start');
            $table->dropConstrainedForeignId('project_id');
            $table->dropConstrainedForeignId('ticket_id');
            $table->dropColumn(['ticket_type', 'activity_classification', 'activity_status', 'activity_date']);
        });
    }
};
