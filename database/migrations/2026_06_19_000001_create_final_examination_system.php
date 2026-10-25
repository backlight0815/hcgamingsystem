<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'final_exam_passed')) {
                $table->boolean('final_exam_passed')->default(false)->after('email_verified_at');
            }

            if (! Schema::hasColumn('users', 'final_exam_passed_at')) {
                $table->timestamp('final_exam_passed_at')->nullable()->after('final_exam_passed');
            }

            if (! Schema::hasColumn('users', 'prop_firm_unlocked')) {
                $table->boolean('prop_firm_unlocked')->default(false)->after('final_exam_passed_at');
            }
        });

        Schema::create('final_exam_questions', function (Blueprint $table): void {
            $table->id();
            $table->string('category', 120);
            $table->string('subcategory', 160)->nullable();
            $table->text('question');
            $table->text('option_a');
            $table->text('option_b');
            $table->text('option_c');
            $table->text('option_d');
            $table->char('correct_answer', 1);
            $table->text('explanation')->nullable();
            $table->string('difficulty', 40)->default('beginner');
            $table->string('status', 40)->default('active');
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['difficulty', 'status']);
            $table->index(['subcategory', 'status']);
        });

        Schema::create('final_exam_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('total_questions')->default(40);
            $table->unsignedTinyInteger('correct_answers')->default(0);
            $table->decimal('score_percentage', 5, 2)->default(0);
            $table->string('status', 40)->default('in_progress');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'submitted_at']);
        });

        Schema::create('final_exam_attempt_questions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('attempt_id')->constrained('final_exam_attempts')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('final_exam_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('position')->default(1);
            $table->char('selected_answer', 1)->nullable();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['attempt_id', 'question_id'], 'final_exam_attempt_question_unique');
            $table->index(['attempt_id', 'position']);
        });

        Schema::create('user_prop_firm_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('prop_firm_model_id')->nullable()->constrained('prop_firm_models')->nullOnDelete();
            $table->foreignId('prop_firm_phase_rule_id')->nullable()->constrained('prop_firm_phase_rules')->nullOnDelete();
            $table->string('prop_firm_model', 160)->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();
        });

        Schema::create('final_exam_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('passing_percentage')->default(60);
            $table->boolean('allow_immediate_retake')->default(true);
            $table->unsignedSmallInteger('retake_cooldown_hours')->default(24);
            $table->boolean('show_review_after_submission')->default(true);
            $table->boolean('existing_users_passed_by_default')->default(true);
            $table->timestamps();
        });

        DB::table('final_exam_settings')->insert([
            'id' => 1,
            'is_active' => true,
            'passing_percentage' => 60,
            'allow_immediate_retake' => true,
            'retake_cooldown_hours' => 24,
            'show_review_after_submission' => true,
            'existing_users_passed_by_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('users')->update([
            'final_exam_passed' => true,
            'final_exam_passed_at' => now(),
            'prop_firm_unlocked' => true,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('final_exam_settings');
        Schema::dropIfExists('user_prop_firm_preferences');
        Schema::dropIfExists('final_exam_attempt_questions');
        Schema::dropIfExists('final_exam_attempts');
        Schema::dropIfExists('final_exam_questions');

        Schema::table('users', function (Blueprint $table): void {
            foreach (['final_exam_passed', 'final_exam_passed_at', 'prop_firm_unlocked'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
