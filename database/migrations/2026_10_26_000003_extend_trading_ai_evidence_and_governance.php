<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->string('canonical_concept_id', 80)->nullable()->index();
            $table->char('content_hash', 64)->nullable()->index();
            $table->string('license_status', 30)->nullable();
            $table->string('source_owner', 150)->nullable();
            $table->timestamp('source_published_at')->nullable();
            $table->timestamp('source_retrieved_at')->nullable();
            $table->timestamp('review_after_at')->nullable();
        });
        DB::table('ai_knowledge_bases')->select(['id', 'content'])->orderBy('id')->chunkById(200, function ($rows): void {
            foreach ($rows as $row) {
                DB::table('ai_knowledge_bases')->where('id', $row->id)->update([
                    'content_hash' => hash('sha256', mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', strip_tags($row->content))))),
                ]);
            }
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->json('claim_evidence')->nullable();
        });
        Schema::table('ai_unanswered_questions', function (Blueprint $table): void {
            $table->string('canonical_concept_id', 80)->nullable()->index();
            $table->string('gap_kind', 40)->nullable();
            $table->unsignedTinyInteger('severity')->default(1);
        });
    }

    public function down(): void
    {
        if (DB::table('ai_chat_messages')->whereNotNull('claim_evidence')->exists()
            || DB::table('ai_knowledge_bases')->whereNotNull('content_hash')->exists()
            || DB::table('ai_unanswered_questions')->whereNotNull('canonical_concept_id')->exists()) {
            throw new RuntimeException('Export Phase 2 evidence, article hashes and concept triage before rolling back this migration.');
        }
        Schema::table('ai_unanswered_questions', function (Blueprint $table): void {
            $table->dropColumn(['canonical_concept_id', 'gap_kind', 'severity']);
        });
        Schema::table('ai_chat_messages', function (Blueprint $table): void {
            $table->dropColumn('claim_evidence');
        });
        Schema::table('ai_knowledge_bases', function (Blueprint $table): void {
            $table->dropColumn(['canonical_concept_id', 'content_hash', 'license_status',
                'source_owner', 'source_published_at', 'source_retrieved_at', 'review_after_at']);
        });
    }
};
