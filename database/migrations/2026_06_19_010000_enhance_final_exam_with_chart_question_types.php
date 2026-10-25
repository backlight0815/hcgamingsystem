<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('final_exam_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('final_exam_questions', 'question_type')) {
                $table->string('question_type', 40)->default('text_mcq')->after('subcategory');
            }

            if (! Schema::hasColumn('final_exam_questions', 'scenario_text')) {
                $table->text('scenario_text')->nullable()->after('question');
            }

            if (! Schema::hasColumn('final_exam_questions', 'chart_image_path')) {
                $table->string('chart_image_path')->nullable()->after('scenario_text');
            }

            if (! Schema::hasColumn('final_exam_questions', 'chart_data_json')) {
                $table->json('chart_data_json')->nullable()->after('chart_image_path');
            }

            if (! Schema::hasColumn('final_exam_questions', 'chart_template_key')) {
                $table->string('chart_template_key', 120)->nullable()->after('chart_data_json');
            }

            if (! Schema::hasColumn('final_exam_questions', 'chart_timeframe')) {
                $table->string('chart_timeframe', 20)->nullable()->after('chart_template_key');
            }
        });

        Schema::table('final_exam_attempts', function (Blueprint $table): void {
            if (! Schema::hasColumn('final_exam_attempts', 'wrong_answers')) {
                $table->unsignedTinyInteger('wrong_answers')->default(0)->after('correct_answers');
            }
        });

        Schema::table('final_exam_attempt_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('final_exam_attempt_questions', 'sort_order')) {
                $table->unsignedTinyInteger('sort_order')->default(1)->after('position');
            }
        });

        DB::table('final_exam_questions')
            ->whereNull('question_type')
            ->orWhere('question_type', '')
            ->update(['question_type' => 'text_mcq']);

        DB::table('final_exam_attempt_questions')
            ->whereColumn('sort_order', '!=', 'position')
            ->update(['sort_order' => DB::raw('position')]);
    }

    public function down(): void
    {
        Schema::table('final_exam_attempt_questions', function (Blueprint $table): void {
            if (Schema::hasColumn('final_exam_attempt_questions', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::table('final_exam_attempts', function (Blueprint $table): void {
            if (Schema::hasColumn('final_exam_attempts', 'wrong_answers')) {
                $table->dropColumn('wrong_answers');
            }
        });

        Schema::table('final_exam_questions', function (Blueprint $table): void {
            foreach ([
                'question_type',
                'scenario_text',
                'chart_image_path',
                'chart_data_json',
                'chart_template_key',
                'chart_timeframe',
            ] as $column) {
                if (Schema::hasColumn('final_exam_questions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
