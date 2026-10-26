<?php

namespace Database\Seeders;

use App\Models\AiKnowledgeBase;
use Illuminate\Database\Seeder;

class TradingAiAdvancedCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $publishLocally = app()->environment(['local', 'testing']);
        foreach (require __DIR__.'/data/trading_ai_advanced_topics.php' as $topic) {
            $normalized = AiKnowledgeBase::normalizeQuestion($topic['title']);
            $exists = AiKnowledgeBase::query()->where('entry_type', 'faq')->where('language', 'en')
                ->where(fn ($query) => $query->where('canonical_concept_id', $topic['id'])
                    ->orWhere('normalized_question', $normalized))->exists();
            if ($exists) continue;
            AiKnowledgeBase::create([
                'entry_type' => 'faq', 'module' => AiKnowledgeBase::MODULE_GENERAL,
                'canonical_concept_id' => $topic['id'], 'language' => 'en',
                'title' => $topic['title'], 'normalized_question' => $normalized,
                'alternative_questions' => $topic['variants'],
                'alternative_normalized' => array_map([AiKnowledgeBase::class, 'normalizeQuestion'], $topic['variants']),
                'content' => $topic['answer'], 'content_hash' => AiKnowledgeBase::contentFingerprint($topic['answer']),
                'status' => $publishLocally ? AiKnowledgeBase::STATUS_PUBLISHED : AiKnowledgeBase::STATUS_DRAFT,
                'approved_at' => $publishLocally ? now() : null,
                'published_at' => $publishLocally ? now() : null,
                'source' => $topic['source_owner'].' reference; HC TradingHub original summary',
                'source_type' => $topic['url'] ? 'primary_source' : 'owner_specification',
                'source_url' => $topic['url'], 'source_owner' => $topic['source_owner'],
                'source_retrieved_at' => $topic['url'] ? now() : null,
                'license_status' => $topic['url'] ? 'link_only' : 'owner_original',
                'content_owner' => 'HC TradingHub', 'last_verified_at' => now(),
                'review_after_at' => now()->addDays($topic['review_days']),
                'difficulty' => 'advanced', 'asset_classes' => $topic['assets'], 'version' => 1,
                'reviewed_at' => now()->toDateString(),
            ]);
        }
    }
}
