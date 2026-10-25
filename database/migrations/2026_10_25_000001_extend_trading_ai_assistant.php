<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->string('difficulty', 20)->default('beginner');
            $table->string('source', 255)->default('HC TradingHub editorial');
            $table->string('language', 12)->default('en');
            $table->unsignedInteger('version')->default(1);
            $table->date('reviewed_at')->nullable();
            $table->index(['module', 'status', 'language']);
        });
        Schema::create('ai_knowledge_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('knowledge_id')->constrained('ai_knowledge_bases')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('title');
            $table->longText('content');
            $table->string('source', 255);
            $table->string('status', 30);
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['knowledge_id', 'version']);
        });

        Schema::create('ai_chat_conversations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 120);
            $table->string('symbol', 30)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });
        Schema::create('ai_chat_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('ai_chat_conversations')->cascadeOnDelete();
            $table->string('role', 12);
            $table->text('content');
            $table->json('sources')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('ai_chat_preferences', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->string('experience', 20)->default('beginner');
            $table->string('style', 20)->default('intraday');
            $table->string('timeframe', 12)->default('M15');
            $table->decimal('risk_percent', 4, 2)->default(0.50);
            $table->decimal('maximum_risk_percent', 4, 2)->default(1.00);
            $table->decimal('preferred_rr', 4, 2)->default(2.00);
            $table->string('preferred_instruments', 255)->nullable();
            $table->string('language', 12)->default('en');
            $table->string('answer_detail', 20)->default('standard');
            $table->timestamps();
        });
        Schema::create('ai_chat_settings', function (Blueprint $table): void {
            $table->string('key', 50)->primary();
            $table->string('value', 255);
            $table->timestamps();
        });
        Schema::create('ai_chat_usage', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('started');
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
        Schema::create('ai_chat_feedback', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('message_id')->constrained('ai_chat_messages')->cascadeOnDelete();
            $table->string('rating', 20);
            $table->string('comment', 500)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_feedback');
        Schema::dropIfExists('ai_chat_usage');
        Schema::dropIfExists('ai_chat_settings');
        Schema::dropIfExists('ai_chat_preferences');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_conversations');
        Schema::dropIfExists('ai_knowledge_versions');
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->dropIndex(['module', 'status', 'language']);
            $table->dropColumn(['difficulty', 'source', 'language', 'version', 'reviewed_at']);
        });
    }
};
