<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'prop_firm_review_status')) {
                $table->string('prop_firm_review_status')->default('none')->after('funded_status');
            }

            if (! Schema::hasColumn('users', 'prop_firm_review_phase')) {
                $table->unsignedTinyInteger('prop_firm_review_phase')->nullable()->after('prop_firm_review_status');
            }

            if (! Schema::hasColumn('users', 'prop_firm_trade_locked')) {
                $table->boolean('prop_firm_trade_locked')->default(false)->after('prop_firm_review_phase');
            }

            if (! Schema::hasColumn('users', 'prop_firm_review_note')) {
                $table->text('prop_firm_review_note')->nullable()->after('prop_firm_trade_locked');
            }

            if (! Schema::hasColumn('users', 'prop_firm_review_requested_at')) {
                $table->timestamp('prop_firm_review_requested_at')->nullable()->after('prop_firm_review_note');
            }

            if (! Schema::hasColumn('users', 'prop_firm_review_approved_at')) {
                $table->timestamp('prop_firm_review_approved_at')->nullable()->after('prop_firm_review_requested_at');
            }
        });

        Schema::create('prop_firm_evaluation_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('asked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedTinyInteger('phase')->nullable();
            $table->string('status')->default('open');
            $table->string('title')->nullable();
            $table->text('question');
            $table->text('answer')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prop_firm_evaluation_questions');

        Schema::table('users', function (Blueprint $table) {
            foreach ([
                'prop_firm_review_status',
                'prop_firm_review_phase',
                'prop_firm_trade_locked',
                'prop_firm_review_note',
                'prop_firm_review_requested_at',
                'prop_firm_review_approved_at',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
