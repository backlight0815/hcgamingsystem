<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->string('qat_resolution', 32)->nullable()->index();
            $table->timestamp('qa_estimated_completion_at')->nullable();
            $table->foreignId('qa_estimated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        $mapping = [
            'New' => 'Open', 'Triaged' => 'Open', 'Assigned' => 'Open',
            'Fixed' => 'Code Review', 'Ready for Retest' => 'Ready for QAT',
            'Retesting' => 'QAT in Progress', 'Verified' => 'QAT Done',
            'Closed' => 'QAT Done', 'Reopened' => 'Reopen', 'Blocked' => 'Open',
        ];
        foreach ($mapping as $from => $to) {
            DB::table('pm_work_items')->where('classification_key', 'QAT')->where('status', $from)
                ->orderBy('id')->chunkById(500, function ($tickets) use ($from, $to): void {
                    foreach ($tickets as $ticket) {
                        DB::transaction(function () use ($ticket, $from, $to): void {
                            DB::table('pm_work_items')->where('id', $ticket->id)->where('status', $from)->update(['status' => $to]);
                            DB::table('pm_issue_history')->insert([
                                'issue_id' => $ticket->id, 'actor_id' => $ticket->reporter_id, 'event' => 'status_migration',
                                'from_status' => $from, 'to_status' => $to,
                                'reason' => 'System migration to QAT workflow 2.1; reporter ID is used only for history foreign-key compatibility.', 'created_at' => now(),
                            ]);
                            DB::table('pm_ticket_activity')->insert([
                                'issue_id' => $ticket->id, 'actor_id' => null, 'actor_name' => null,
                                'event_type' => 'status', 'field_name' => 'status',
                                'old_value' => $from, 'new_value' => $to,
                                'metadata' => json_encode(['migration' => 'pms_qat_2_1']), 'created_at' => now(),
                            ]);
                        });
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('pm_work_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('qa_estimated_by');
            $table->dropColumn('qa_estimated_completion_at');
            $table->dropColumn('qat_resolution');
        });
    }
};
