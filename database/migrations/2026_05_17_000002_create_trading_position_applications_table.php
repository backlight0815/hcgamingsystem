<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['id' => 760],
            ['name' => 'Leadership', 'description' => 'Trading Leadership']
        );

        DB::table('roles')->updateOrInsert(
            ['id' => 770],
            ['name' => 'Recruiter', 'description' => 'Trading Recruiter']
        );

        if (! Schema::hasTable('trading_position_applications')) {
            Schema::create('trading_position_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('requested_position')->index();
                $table->unsignedInteger('requested_role_id');
                $table->string('status')->default('pending')->index();
                $table->date('first_trade_date')->nullable();
                $table->unsignedInteger('trade_count_snapshot')->default(0);
                $table->text('strategy_summary')->nullable();
                $table->text('trade_history_summary')->nullable();
                $table->text('personality_summary')->nullable();
                $table->text('marketing_plan')->nullable();
                $table->text('client_support_plan')->nullable();
                $table->string('supporting_document_path')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('review_note')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
            });
        }

        Schema::table('trading_recordings', function (Blueprint $table) {
            if (! Schema::hasColumn('trading_recordings', 'approval_status')) {
                $table->string('approval_status')->default('approved')->after('status');
            }

            if (! Schema::hasColumn('trading_recordings', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('trading_recordings', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('trading_recordings', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });

        Schema::table('knowledge_centres', function (Blueprint $table) {
            if (! Schema::hasColumn('knowledge_centres', 'uploaded_by')) {
                $table->foreignId('uploaded_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('knowledge_centres', 'approval_status')) {
                $table->string('approval_status')->default('approved')->after('uploaded_by');
            }

            if (! Schema::hasColumn('knowledge_centres', 'approved_by')) {
                $table->foreignId('approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('knowledge_centres', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('approved_by');
            }

            if (! Schema::hasColumn('knowledge_centres', 'approval_note')) {
                $table->text('approval_note')->nullable()->after('approved_at');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_position_applications');

        Schema::table('trading_recordings', function (Blueprint $table) {
            if (Schema::hasColumn('trading_recordings', 'approved_by')) {
                try {
                    $table->dropForeign(['approved_by']);
                } catch (\Throwable) {
                    //
                }
            }
        });

        Schema::table('trading_recordings', function (Blueprint $table) {
            foreach (['approval_note', 'approved_at', 'approved_by', 'approval_status'] as $column) {
                if (Schema::hasColumn('trading_recordings', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('knowledge_centres', function (Blueprint $table) {
            foreach (['approved_by', 'uploaded_by'] as $column) {
                if (Schema::hasColumn('knowledge_centres', $column)) {
                    try {
                        $table->dropForeign([$column]);
                    } catch (\Throwable) {
                        //
                    }
                }
            }
        });

        Schema::table('knowledge_centres', function (Blueprint $table) {
            foreach (['approval_note', 'approved_at', 'approved_by', 'approval_status', 'uploaded_by'] as $column) {
                if (Schema::hasColumn('knowledge_centres', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
