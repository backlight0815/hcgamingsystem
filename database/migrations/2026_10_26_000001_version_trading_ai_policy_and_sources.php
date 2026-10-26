<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_chat_prompts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('version')->unique();
            $table->longText('content');
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->json('asset_classes')->nullable();
            $table->string('source_type', 40)->nullable();
            $table->string('source_url', 500)->nullable();
            $table->string('content_owner', 100)->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('effective_until')->nullable();
            $table->foreignId('superseded_by')->nullable()->constrained('ai_knowledge_bases')->nullOnDelete();
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropUnique(['reply_to_message_id']);
            $table->index('reply_to_message_id');
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('model', 100)->nullable();
            $table->string('answer_type', 40)->nullable();
            $table->timestamp('completed_at')->nullable();
        });
        Schema::table('ai_chat_feedback', function (Blueprint $table): void {
            $table->string('issue_category', 30)->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution')->nullable();
        });
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->uuid('request_id')->nullable()->unique();
            $table->unsignedInteger('prompt_version')->nullable();
            $table->string('model', 100)->nullable();
            $table->unsignedSmallInteger('retrieval_hits')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::table('ai_chat_messages')->whereNotNull('reply_to_message_id')
            ->select('reply_to_message_id')->groupBy('reply_to_message_id')
            ->havingRaw('COUNT(*) > 1')->exists()
            || DB::table('ai_chat_prompts')->where('version', '>', 3)->exists()) {
            throw new RuntimeException('Back up AI data and remove additional answer attempts or prompt revisions before rolling back this migration.');
        }
        Schema::table('ai_chat_usage', function (Blueprint $table): void {
            $table->dropUnique(['request_id']);
            $table->dropColumn(['request_id', 'prompt_version', 'model', 'retrieval_hits']);
        });
        Schema::table('ai_chat_feedback', function (Blueprint $table): void {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn(['issue_category', 'reviewed_at', 'reviewer_id', 'resolution']);
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropIndex(['reply_to_message_id']);
            $table->unique('reply_to_message_id');
            $table->dropColumn(['prompt_version', 'model', 'answer_type', 'completed_at']);
        });
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->dropForeign(['superseded_by']);
            $table->dropColumn(['asset_classes', 'source_type', 'source_url', 'content_owner',
                'last_verified_at', 'effective_until', 'superseded_by']);
        });
        Schema::dropIfExists('ai_chat_prompts');
    }
};
