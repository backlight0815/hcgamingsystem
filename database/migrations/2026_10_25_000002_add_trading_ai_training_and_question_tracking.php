<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_knowledge_versions', function (Blueprint $table): void {
            $table->json('metadata')->nullable();
        });
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->string('entry_type', 20)->default('article')->index();
            $table->string('normalized_question')->nullable();
            $table->json('alternative_questions')->nullable();
            $table->json('alternative_normalized')->nullable();
            $table->json('keywords')->nullable();
            $table->unsignedSmallInteger('priority')->default(0);
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('last_tested_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unique(['entry_type', 'language', 'normalized_question'], 'ai_training_question_unique');
        });

        Schema::create('ai_chat_response_settings', function (Blueprint $table): void {
            $table->string('key', 50)->primary();
            $table->text('value');
            $table->timestamps();
        });
        Schema::table('ai_chat_conversations', function (Blueprint $table): void {
            $table->string('module', 30)->default('tradinghub')->index();
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->string('client_message_id', 64)->nullable()->unique();
            $table->string('status', 20)->default('answered')->index();
            $table->string('response_source', 30)->nullable();
            $table->string('language', 12)->default('en');
            $table->unsignedBigInteger('reply_to_message_id')->nullable()->unique();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->string('provider_failure_code', 40)->nullable();
        });
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->string('response_source', 30)->nullable();
            $table->string('provider_failure_code', 40)->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
        });
        Schema::create('ai_unanswered_questions', function (Blueprint $table): void {
            $table->id();
            $table->char('question_hash', 64)->unique();
            $table->text('question');
            $table->string('category', 80)->nullable();
            $table->unsignedInteger('frequency')->default(1);
            $table->string('status', 20)->default('pending')->index();
            $table->foreignId('training_id')->nullable()->constrained('ai_knowledge_bases')->nullOnDelete();
            $table->timestamp('last_asked_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_unanswered_questions');
        Schema::dropIfExists('ai_chat_response_settings');
        Schema::table('ai_knowledge_versions', function (Blueprint $table): void {
            $table->dropColumn('metadata');
        });
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->dropColumn(['response_source', 'provider_failure_code', 'response_time_ms']);
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropUnique(['client_message_id']);
            $table->dropIndex(['status']);
            $table->dropUnique(['reply_to_message_id']);
            $table->dropColumn(['client_message_id', 'status', 'response_source', 'language',
                'reply_to_message_id', 'response_time_ms', 'provider_failure_code']);
        });
        Schema::table('ai_chat_conversations', function (Blueprint $table): void {
            $table->dropIndex(['module']);
            $table->dropColumn('module');
        });
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->dropUnique('ai_training_question_unique');
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['approved_by']);
            $table->dropIndex(['entry_type']);
            $table->dropColumn(['entry_type', 'normalized_question', 'alternative_questions', 'alternative_normalized', 'keywords',
                'priority', 'effective_at', 'approved_at', 'published_at', 'last_tested_at',
                'created_by', 'updated_by', 'approved_by']);
        });
    }
};
