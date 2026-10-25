<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_global_cycle_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('stream_key', 32)->unique();
            $table->boolean('auto_enabled')->default(true);
            $table->unsignedSmallInteger('interval_days')->default(45);
            $table->date('anchor_date');
            $table->string('timezone', 64);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        DB::table('pm_global_cycle_settings')->insert([
            'stream_key' => 'main', 'auto_enabled' => true, 'interval_days' => 45,
            'anchor_date' => now(config('app.timezone'))->toDateString(),
            'timezone' => config('app.timezone'), 'created_at' => now(), 'updated_at' => now(),
        ]);

        Schema::create('pm_global_cycles', function (Blueprint $table): void {
            $table->id();
            $table->string('stream_key', 32)->default('main');
            $table->string('cycle_key', 32)->unique();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->date('baseline_start_date');
            $table->date('baseline_end_date');
            $table->string('status', 20)->default('Upcoming');
            $table->boolean('automated')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->unique(['stream_key', 'start_date']);
            $table->index(['stream_key', 'end_date', 'status']);
        });
        Schema::create('pm_global_cycle_stages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_global_cycles')->cascadeOnDelete();
            $table->string('stage_key', 32);
            $table->string('name', 80);
            $table->unsignedTinyInteger('sort_order');
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedTinyInteger('completion_percent')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['cycle_id', 'stage_key']);
            $table->unique(['cycle_id', 'sort_order']);
        });
        Schema::create('pm_global_cycle_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_global_cycles')->cascadeOnDelete();
            $table->foreignId('project_id')->constrained('pm_projects')->cascadeOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['cycle_id', 'project_id']);
        });
        Schema::create('pm_global_cycle_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cycle_id')->constrained('pm_global_cycles')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 48);
            $table->text('reason')->nullable();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['cycle_id', 'created_at']);
        });
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->foreignId('global_cycle_id')->nullable()->constrained('pm_global_cycles')->nullOnDelete();
            $table->foreignId('original_global_cycle_id')->nullable()->constrained('pm_global_cycles')->nullOnDelete();
            $table->index(['global_cycle_id', 'status']);
        });
        Schema::table('pm_releases', function (Blueprint $table): void {
            $table->foreignId('global_cycle_id')->nullable()->constrained('pm_global_cycles')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pm_releases', fn (Blueprint $table) => $table->dropConstrainedForeignId('global_cycle_id'));
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropIndex(['global_cycle_id', 'status']);
            $table->dropConstrainedForeignId('original_global_cycle_id');
            $table->dropConstrainedForeignId('global_cycle_id');
        });
        Schema::dropIfExists('pm_global_cycle_history');
        Schema::dropIfExists('pm_global_cycle_projects');
        Schema::dropIfExists('pm_global_cycle_stages');
        Schema::dropIfExists('pm_global_cycles');
        Schema::dropIfExists('pm_global_cycle_settings');
    }
};
