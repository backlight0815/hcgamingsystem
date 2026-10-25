<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_workspace_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('registration_policy', 24)->default('approval');
            $table->string('week_mode', 16)->default('calendar');
            $table->json('weekend_days')->nullable();
            $table->json('holidays')->nullable();
            $table->json('health_thresholds')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('pms_workspace_settings')->insert([
            'registration_policy' => 'approval',
            'week_mode' => 'calendar',
            'weekend_days' => json_encode([0, 6]),
            'holidays' => json_encode([]),
            'health_thresholds' => json_encode(['attention_gap' => 10, 'risk_gap' => 20, 'reopen_limit' => 2]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('pms_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('department')->nullable();
            $table->string('job_title')->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('deactivated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pms_registrations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 24)->default('public');
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('invitation_expires_at')->nullable();
            $table->timestamps();
        });

        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->unsignedInteger('next_cycle_number')->default(1)->after('next_issue_number');
            $table->timestamp('archived_at')->nullable()->index()->after('completed_at');
            $table->foreignId('archived_by')->nullable()->after('archived_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('pm_cycles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->unsignedInteger('cycle_number');
            $table->string('cycle_key', 40)->unique();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('Planned')->index();
            $table->string('week_mode', 16)->default('calendar');
            $table->unsignedTinyInteger('template_weeks')->nullable();
            $table->date('start_date');
            $table->date('target_end_date')->index();
            $table->date('baseline_start_date');
            $table->date('baseline_end_date');
            $table->date('forecast_end_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedSmallInteger('development_days');
            $table->unsignedSmallInteger('internal_testing_days');
            $table->unsignedSmallInteger('qat_days');
            $table->unsignedSmallInteger('buffer_days')->default(0);
            $table->timestamp('scope_baselined_at')->nullable();
            $table->text('schedule_change_reason')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['project_id', 'cycle_number']);
            $table->index(['project_id', 'status', 'start_date']);
        });

        Schema::create('pm_cycle_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_cycles')->cascadeOnDelete();
            $table->string('phase_key', 24);
            $table->string('name');
            $table->unsignedTinyInteger('sort_order');
            $table->date('planned_start_date');
            $table->date('planned_end_date');
            $table->date('actual_start_date')->nullable();
            $table->date('actual_end_date')->nullable();
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->boolean('signed_off')->default(false);
            $table->foreignId('signed_off_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('signed_off_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'phase_key']);
        });

        Schema::create('pm_cycle_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_cycles')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event', 40);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cycle_id', 'created_at']);
        });

        Schema::create('pm_cycle_scope_baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_cycles')->cascadeOnDelete();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->decimal('planned_units', 10, 2)->default(1);
            $table->string('unit_basis', 20)->default('issue_count');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['cycle_id', 'issue_id']);
        });

        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->foreignId('cycle_id')->nullable()->after('project_id')->constrained('pm_cycles')->nullOnDelete();
            $table->foreignId('original_cycle_id')->nullable()->after('cycle_id')->constrained('pm_cycles')->nullOnDelete();
            $table->string('classification_key', 3)->nullable()->after('issue_key')->index();
            $table->unsignedInteger('display_sequence')->nullable()->after('classification_key');
            $table->decimal('story_points', 8, 2)->nullable()->after('estimate_minutes');
            $table->unsignedSmallInteger('carryover_count')->default(0)->after('reopen_count');
            $table->string('target_release')->nullable();
            $table->text('business_objective')->nullable();
            $table->text('business_justification')->nullable();
            $table->text('expected_value')->nullable();
            $table->text('current_behavior')->nullable();
            $table->text('proposed_behavior')->nullable();
            $table->string('affected_component')->nullable();
            $table->text('backward_compatibility')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->string('production_environment')->nullable();
            $table->string('affected_module')->nullable();
            $table->text('business_impact')->nullable();
            $table->unsignedInteger('affected_users_estimate')->nullable();
            $table->longText('reproduction_steps')->nullable();
            $table->longText('expected_result')->nullable();
            $table->longText('actual_result')->nullable();
            $table->longText('root_cause')->nullable();
            $table->text('workaround')->nullable();
            $table->string('fix_version')->nullable();
            $table->boolean('hotfix_required')->default(false);
            $table->boolean('fast_track')->default(false);
            $table->text('fast_track_reason')->nullable();
            $table->foreignId('fast_track_approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('incident_resolved_at')->nullable();
            $table->longText('post_incident_review')->nullable();
            $table->index(['cycle_id', 'status']);
            $table->index(['project_id', 'classification_key', 'created_at'], 'pm_work_items_classification_report_idx');
        });
    }

    public function down(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropForeign(['cycle_id']);
            $table->dropForeign(['original_cycle_id']);
            $table->dropForeign(['fast_track_approved_by']);
            $table->dropIndex('pm_work_items_classification_report_idx');
            $table->dropColumn([
                'cycle_id', 'original_cycle_id', 'classification_key', 'display_sequence', 'story_points', 'carryover_count',
                'target_release', 'business_objective', 'business_justification', 'expected_value', 'current_behavior',
                'proposed_behavior', 'affected_component', 'backward_compatibility', 'detected_at', 'production_environment',
                'affected_module', 'business_impact', 'affected_users_estimate', 'reproduction_steps', 'expected_result',
                'actual_result', 'root_cause', 'workaround', 'fix_version', 'hotfix_required', 'fast_track',
                'fast_track_reason', 'fast_track_approved_by', 'incident_resolved_at', 'post_incident_review',
            ]);
        });
        Schema::dropIfExists('pm_cycle_scope_baselines');
        Schema::dropIfExists('pm_cycle_history');
        Schema::dropIfExists('pm_cycle_phases');
        Schema::dropIfExists('pm_cycles');
        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['next_cycle_number', 'archived_at', 'archived_by']);
        });
        Schema::dropIfExists('pms_registrations');
        Schema::dropIfExists('pms_profiles');
        Schema::dropIfExists('pms_workspace_settings');
    }
};
