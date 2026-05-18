<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trading_exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category')->nullable();
            $table->string('difficulty')->default('foundation');
            $table->text('question_text');
            $table->text('explanation')->nullable();
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['created_by', 'status']);
        });

        Schema::create('trading_exam_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_exam_question_id')->constrained('trading_exam_questions')->cascadeOnDelete();
            $table->text('option_text');
            $table->boolean('is_correct')->default(false);
            $table->unsignedTinyInteger('position')->default(1);
            $table->timestamps();

            $table->index(['trading_exam_question_id', 'position']);
        });

        Schema::create('trading_exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('exam_date');
            $table->string('status')->default('in_progress');
            $table->unsignedTinyInteger('total_questions')->default(5);
            $table->unsignedTinyInteger('score')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'exam_date']);
            $table->index(['exam_date', 'status']);
        });

        Schema::create('trading_exam_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trading_exam_attempt_id')->constrained('trading_exam_attempts')->cascadeOnDelete();
            $table->foreignId('trading_exam_question_id')->constrained('trading_exam_questions')->cascadeOnDelete();
            $table->foreignId('selected_option_id')->nullable()->constrained('trading_exam_options')->nullOnDelete();
            $table->boolean('is_correct')->default(false);
            $table->timestamps();

            $table->unique(['trading_exam_attempt_id', 'trading_exam_question_id'], 'exam_answer_attempt_question_unique');
        });

        Schema::create('trading_exam_quota_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leader_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('current_limit')->default(50);
            $table->unsignedSmallInteger('requested_limit');
            $table->text('reason');
            $table->string('status')->default('pending');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['leader_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trading_exam_quota_requests');
        Schema::dropIfExists('trading_exam_answers');
        Schema::dropIfExists('trading_exam_attempts');
        Schema::dropIfExists('trading_exam_options');
        Schema::dropIfExists('trading_exam_questions');
    }
};
