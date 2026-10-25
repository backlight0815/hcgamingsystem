<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_global_cycle_settings', function (Blueprint $table): void {
            $table->json('default_stage_durations')->nullable();
        });
        DB::table('pm_global_cycle_settings')->where('stream_key', 'main')->update([
            'default_stage_durations' => json_encode([
                'planning' => 3, 'development' => 14, 'internal_testing' => 7,
                'qat' => 14, 'preparation' => 6, 'closure' => 1,
            ]),
        ]);
        Schema::table('pm_global_cycles', function (Blueprint $table): void {
            $table->text('notes')->nullable();
            $table->date('production_target_date')->nullable();
        });
        Schema::create('pms_departments', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('pms_job_positions', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('pms_department_positions', function (Blueprint $table): void {
            $table->foreignId('department_id')->constrained('pms_departments')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('pms_job_positions')->cascadeOnDelete();
            $table->primary(['department_id', 'position_id']);
        });
        $defaults = [
            ['BA', 'Business Analysis', 'BA', 'Business Analyst'],
            ['DEV', 'Development / Engineering', 'DEV', 'Developer'],
            ['QA', 'Quality Assurance', 'QA', 'Quality Assurance Engineer'],
            ['PM', 'Project Management', 'PM', 'Project Manager'],
            ['FIN', 'Finance', 'FIN', 'Finance'],
            ['HR', 'Human Resources', 'HR', 'Human Resource Manager'],
        ];
        foreach ($defaults as [$departmentCode, $department, $positionCode, $position]) {
            $departmentId = DB::table('pms_departments')->insertGetId([
                'code' => $departmentCode, 'name' => $department, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $positionId = DB::table('pms_job_positions')->insertGetId([
                'code' => $positionCode, 'name' => $position, 'active' => true,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('pms_department_positions')->insert(['department_id' => $departmentId, 'position_id' => $positionId]);
        }
        Schema::table('pms_profiles', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained('pms_job_positions')->nullOnDelete();
            $table->index(['department_id', 'job_position_id']);
        });
        foreach (DB::table('pms_departments')->get() as $department) {
            DB::table('pms_profiles')->whereRaw('LOWER(TRIM(department)) = ?', [strtolower($department->name)])
                ->update(['department_id' => $department->id]);
        }
        foreach (DB::table('pms_job_positions')->get() as $position) {
            $names = $position->name === 'Quality Assurance Engineer'
                ? ['Quality Assurance Engineer', 'QA Engineer'] : [$position->name];
            DB::table('pms_profiles')->whereIn('job_position', $names)->update(['job_position_id' => $position->id]);
        }
        Schema::table('pms_invitations', function (Blueprint $table): void {
            $table->string('invited_name')->nullable();
            $table->foreignId('department_id')->nullable()->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('job_position_id')->nullable()->constrained('pms_job_positions')->nullOnDelete();
        });
        Schema::table('pm_cycles', function (Blueprint $table): void {
            $table->foreignId('global_cycle_id')->nullable()->constrained('pm_global_cycles')->nullOnDelete();
            $table->index(['global_cycle_id', 'project_id']);
        });
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->foreignId('global_cycle_stage_id')->nullable()->constrained('pm_global_cycle_stages')->nullOnDelete();
            $table->index(['global_cycle_id', 'global_cycle_stage_id']);
        });
        // Only explicit ticket relationships are evidence of a shared cycle. Leave all
        // ambiguous legacy schedules untouched for administrator reconciliation.
        foreach (DB::table('pm_cycles')->select('id')->cursor() as $legacy) {
            $tickets = DB::table('pm_work_items')->where('cycle_id', $legacy->id);
            $total = (clone $tickets)->count();
            $linked = (clone $tickets)->whereNotNull('global_cycle_id')->count();
            $ids = (clone $tickets)->whereNotNull('global_cycle_id')
                ->distinct()->pluck('global_cycle_id');
            if ($total > 0 && $total === $linked && $ids->count() === 1) {
                DB::table('pm_cycles')->where('id', $legacy->id)->update(['global_cycle_id' => $ids->first()]);
            }
        }
        Schema::create('pm_ticket_blockers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->string('category', 48);
            $table->string('reason', 500);
            $table->text('details')->nullable();
            $table->foreignId('related_issue_id')->nullable()->constrained('pm_work_items')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('expected_resolution_date')->nullable();
            $table->foreignId('reported_by')->constrained('users');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
            $table->index(['issue_id', 'resolved_at']);
        });
        Schema::create('pm_ticket_blocker_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blocker_id')->constrained('pm_ticket_blockers')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users');
            $table->string('event', 24);
            $table->json('before')->nullable();
            $table->json('after')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['blocker_id', 'created_at']);
        });
        Schema::create('pm_ticket_contributors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('issue_id')->constrained('pm_work_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('assignment_type', 16);
            $table->foreignId('assigned_by')->constrained('users');
            $table->timestamps();
            $table->unique(['issue_id', 'user_id', 'assignment_type'], 'pm_ticket_contributors_unique');
            $table->index(['user_id', 'assignment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_ticket_contributors');
        Schema::dropIfExists('pm_ticket_blocker_history');
        Schema::dropIfExists('pm_ticket_blockers');
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropIndex(['global_cycle_id', 'global_cycle_stage_id']);
            $table->dropConstrainedForeignId('global_cycle_stage_id');
        });
        Schema::table('pm_cycles', function (Blueprint $table): void {
            $table->dropIndex(['global_cycle_id', 'project_id']);
            $table->dropConstrainedForeignId('global_cycle_id');
        });
        Schema::table('pms_invitations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('invited_name');
        });
        Schema::table('pms_profiles', function (Blueprint $table): void {
            $table->dropIndex(['department_id', 'job_position_id']);
            $table->dropConstrainedForeignId('job_position_id');
            $table->dropConstrainedForeignId('department_id');
        });
        Schema::dropIfExists('pms_department_positions');
        Schema::dropIfExists('pms_job_positions');
        Schema::dropIfExists('pms_departments');
        Schema::table('pm_global_cycles', fn (Blueprint $table) => $table->dropColumn(['notes', 'production_target_date']));
        Schema::table('pm_global_cycle_settings', fn (Blueprint $table) => $table->dropColumn('default_stage_durations'));
    }
};
