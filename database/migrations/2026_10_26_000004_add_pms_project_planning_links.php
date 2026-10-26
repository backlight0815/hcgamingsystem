<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->foreignId('current_global_cycle_id')->nullable()->constrained('pm_global_cycles')->nullOnDelete();
            $table->foreignId('current_release_id')->nullable()->constrained('pm_releases')->nullOnDelete();
        });
        Schema::create('pm_project_releases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('release_id')->constrained('pm_releases')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'release_id']);
            $table->index(['release_id', 'project_id']);
        });
        DB::table('pm_releases')->orderBy('id')->chunkById(200, function ($releases): void {
            foreach ($releases as $release) {
                DB::table('pm_project_releases')->insertOrIgnore([
                    'project_id' => $release->project_id, 'release_id' => $release->id,
                    'assigned_by' => $release->created_by, 'created_at' => $release->created_at,
                    'updated_at' => $release->updated_at,
                ]);
            }
        });
        DB::table('pm_global_cycle_projects')->select('project_id')->groupBy('project_id')
            ->havingRaw('count(*) = 1')->orderBy('project_id')->chunk(200, function ($rows): void {
                foreach ($rows as $row) {
                    $cycleId = DB::table('pm_global_cycle_projects')->where('project_id', $row->project_id)->value('cycle_id');
                    DB::table('pm_projects')->where('id', $row->project_id)->update(['current_global_cycle_id' => $cycleId]);
                }
            });
    }

    public function down(): void
    {
        if (DB::table('pm_projects')->whereNotNull('current_global_cycle_id')
            ->orWhereNotNull('current_release_id')->exists()) {
            throw new \RuntimeException('Export selected project cycle/release pointers before rolling back this migration.');
        }
        if (DB::table('pm_project_releases')->whereRaw('project_id <> (select project_id from pm_releases where pm_releases.id = pm_project_releases.release_id)')->exists()) {
            throw new \RuntimeException('Export cross-project release links before rolling back this migration.');
        }
        Schema::dropIfExists('pm_project_releases');
        Schema::table('pm_projects', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('current_release_id');
            $table->dropConstrainedForeignId('current_global_cycle_id');
        });
    }
};
