<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('pm_ticket_blockers', function (Blueprint $table): void {
            $table->unsignedTinyInteger('status_code')->default(1)->index();
        });
        \Illuminate\Support\Facades\DB::table('pm_ticket_blockers')->whereNotNull('resolved_at')->update(['status_code' => 3]);
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->unsignedInteger('hold_previous_status_code')->nullable();
            $table->text('hold_reason')->nullable();
            $table->foreignId('hold_blocker_id')->nullable()->constrained('pm_ticket_blockers')->nullOnDelete();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('held_at')->nullable();
            $table->index(['status_code', 'held_at'], 'pm_ticket_hold_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropIndex('pm_ticket_hold_status_index');
            $table->dropConstrainedForeignId('held_by');
            $table->dropConstrainedForeignId('hold_blocker_id');
            $table->dropColumn(['hold_previous_status_code', 'hold_reason', 'held_at']);
        });
        Schema::table('pm_ticket_blockers', function (Blueprint $table): void {
            $table->dropIndex(['status_code']);
            $table->dropColumn('status_code');
        });
    }
};
