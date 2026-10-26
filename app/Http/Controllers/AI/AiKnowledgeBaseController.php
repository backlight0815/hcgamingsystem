<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeBase;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

class AiKnowledgeBaseController extends Controller
{
    private const ADMIN_ROLES = [1, 2];

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = AiKnowledgeBase::query()->where('entry_type', 'article')->latest();

        if ($request->filled('module') && array_key_exists($request->module, AiKnowledgeBase::modules())) {
            $query->where('module', $request->module);
        }

        if ($request->filled('status') && array_key_exists($request->status, AiKnowledgeBase::statuses())) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('title', 'like', '%'.$search.'%')
                    ->orWhere('content', 'like', '%'.$search.'%');
            });
        }

        $knowledgeBases = $query->paginate(20)->withQueryString();
        $modules = AiKnowledgeBase::modules();
        $statuses = AiKnowledgeBase::statuses();

        return view('admin.ai_knowledge_base.index', compact('knowledgeBases', 'modules', 'statuses'));
    }

    public function create()
    {
        $this->ensureAdmin();

        $modules = AiKnowledgeBase::modules();
        $statuses = ['draft' => 'Draft'];

        return view('admin.ai_knowledge_base.create', compact('modules', 'statuses'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        AiKnowledgeBase::create(array_merge($this->validatedKnowledgeBase($request), [
            'entry_type' => 'article', 'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            'status' => AiKnowledgeBase::STATUS_DRAFT,
        ]));

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record created successfully.');
    }

    public function edit(AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();
        abort_unless($knowledgeBase->entry_type === 'article', 404);

        $modules = AiKnowledgeBase::modules();
        $statuses = AiKnowledgeBase::statuses();
        $versions = DB::table('ai_knowledge_versions')->where('knowledge_id', $knowledgeBase->id)
            ->orderByDesc('version')->limit(10)->get();

        return view('admin.ai_knowledge_base.edit', compact('knowledgeBase', 'modules', 'statuses', 'versions'));
    }

    public function update(Request $request, AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();
        abort_unless($knowledgeBase->entry_type === 'article', 404);

        $data = $this->validatedKnowledgeBase($request, $knowledgeBase->id);
        DB::transaction(function () use ($knowledgeBase, $data): void {
            $knowledgeBase = AiKnowledgeBase::lockForUpdate()->findOrFail($knowledgeBase->id);
            DB::table('ai_knowledge_versions')->insert([
                'knowledge_id' => $knowledgeBase->id, 'version' => $knowledgeBase->version,
                'title' => $knowledgeBase->title, 'content' => $knowledgeBase->content,
                'source' => $knowledgeBase->source, 'status' => $knowledgeBase->status,
                'metadata' => json_encode(['canonical_concept_id' => $knowledgeBase->canonical_concept_id,
                    'content_hash' => $knowledgeBase->content_hash, 'source_url' => $knowledgeBase->source_url,
                    'license_status' => $knowledgeBase->license_status, 'source_owner' => $knowledgeBase->source_owner,
                    'review_after_at' => $knowledgeBase->review_after_at?->toIso8601String()]),
                'changed_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $legacy = in_array($knowledgeBase->status, [AiKnowledgeBase::STATUS_ACTIVE, AiKnowledgeBase::STATUS_INACTIVE], true);
            if ($legacy && ! in_array($data['status'], [AiKnowledgeBase::STATUS_ACTIVE, AiKnowledgeBase::STATUS_INACTIVE], true)) {
                throw ValidationException::withMessages(['status' => 'Use the review workflow to publish a new article.']);
            }
            $knowledgeBase->update($data + ['version' => $knowledgeBase->version + 1, 'updated_by' => auth()->id()]);
            if (! $legacy) $knowledgeBase->update(['status' => AiKnowledgeBase::STATUS_DRAFT,
                'approved_at' => null, 'approved_by' => null, 'published_at' => null]);
        });

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record updated successfully.');
    }

    public function destroy(AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();
        abort_unless($knowledgeBase->entry_type === 'article', 404);

        $knowledgeBase->update(['status' => in_array($knowledgeBase->status,
            [AiKnowledgeBase::STATUS_ACTIVE, AiKnowledgeBase::STATUS_INACTIVE], true)
            ? AiKnowledgeBase::STATUS_INACTIVE : AiKnowledgeBase::STATUS_ARCHIVED]);

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record unpublished.');
    }

    private function validatedKnowledgeBase(Request $request, ?int $exceptId = null): array
    {
        $data = $request->validate([
            'module' => ['required', 'string', Rule::in(array_keys(AiKnowledgeBase::modules()))],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', 'string', Rule::in(array_keys(AiKnowledgeBase::statuses()))],
            'difficulty' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'source' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:12'],
            'reviewed_at' => ['nullable', 'date'],
            'asset_classes' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', Rule::in(['owner_specification', 'primary_source', 'editorial', 'platform_documentation'])],
            'source_url' => ['nullable', 'url:http,https', 'max:500'],
            'content_owner' => ['nullable', 'string', 'max:100'],
            'last_verified_at' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date'],
            'canonical_concept_id' => ['nullable', Rule::in(array_keys(config('trading_ai_concepts.concepts', [])))],
            'license_status' => ['nullable', Rule::in(['owner_original', 'permission_confirmed', 'link_only', 'unknown'])],
            'source_owner' => ['nullable', 'string', 'max:150'],
            'source_published_at' => ['nullable', 'date'],
            'source_retrieved_at' => ['nullable', 'date'],
            'review_after_at' => ['nullable', 'date', 'after:last_verified_at'],
        ]);
        if (preg_match('/(?:ignore|override|disregard)\s+(?:all\s+)?(?:previous|system|developer)\s+(?:instructions|rules|prompt)|(?:reveal|print|expose)\s+(?:the\s+)?(?:system\s+prompt|api\s+key|secret)/i', $data['content'])) {
            throw ValidationException::withMessages(['content' => 'Instruction-like content cannot be added to the knowledge base.']);
        }
        $data['content_hash'] = AiKnowledgeBase::contentFingerprint($data['content']);
        $language = $data['language'] ?? 'en';
        $duplicate = AiKnowledgeBase::query()->where('entry_type', 'article')->where('language', $language)
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where(fn ($query) => $query->where('content_hash', $data['content_hash'])
                ->orWhere('title', trim($data['title'])))->exists();
        if ($duplicate) throw ValidationException::withMessages(['content' => 'An article with this title or teaching content already exists. Revise its existing record instead.']);
        if (! empty($data['canonical_concept_id']) && ! empty($data['source_url'])
            && AiKnowledgeBase::query()->where('entry_type', 'article')->where('language', $language)
                ->where('canonical_concept_id', $data['canonical_concept_id'])
                ->where('source_url', $data['source_url'])
                ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))->exists()) {
            throw ValidationException::withMessages(['source_url' => 'This source already has an article for the same concept. Revise that record.']);
        }
        if (($data['source_type'] ?? null) === 'primary_source'
            && (empty($data['source_url']) || empty($data['source_owner']) || empty($data['source_retrieved_at']))) {
            throw ValidationException::withMessages(['source_url' => 'Primary sources need a URL, source owner and retrieval date.']);
        }
        $data['license_status'] ??= empty($data['source_url']) ? 'owner_original' : 'link_only';
        $data['asset_classes'] = collect(explode(',', $data['asset_classes'] ?? ''))
            ->map(fn ($item) => strtolower(trim($item)))->filter()->unique()->take(20)->values()->all();
        return $data;
    }

    public function transition(Request $request, AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();
        abort_unless($knowledgeBase->entry_type === 'article', 404);
        $action = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'publish', 'archive'])]])['action'];
        DB::transaction(function () use ($knowledgeBase, $action): void {
            $row = AiKnowledgeBase::lockForUpdate()->findOrFail($knowledgeBase->id);
            $allowed = ['submit' => ['draft'], 'approve' => ['pending_review'],
                'publish' => ['approved'], 'archive' => ['draft', 'pending_review', 'approved', 'published']];
            if (! in_array($row->status, $allowed[$action], true)) {
                throw ValidationException::withMessages(['action' => 'This article transition is unavailable.']);
            }
            if ($action === 'publish' && $row->source_url && $row->license_status === 'unknown') {
                throw ValidationException::withMessages(['action' => 'Resolve source permission status before publication.']);
            }
            DB::table('ai_knowledge_versions')->insert([
                'knowledge_id' => $row->id, 'version' => $row->version,
                'title' => $row->title, 'content' => $row->content,
                'source' => $row->source, 'status' => $row->status,
                'metadata' => json_encode(['canonical_concept_id' => $row->canonical_concept_id,
                    'content_hash' => $row->content_hash, 'source_url' => $row->source_url,
                    'license_status' => $row->license_status, 'source_owner' => $row->source_owner]),
                'changed_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row->update(['status' => match ($action) {
                'submit' => 'pending_review', 'approve' => 'approved',
                'publish' => 'published', 'archive' => 'archived',
            }, 'version' => $row->version + 1, 'updated_by' => auth()->id(),
                'approved_at' => $action === 'approve' ? now() : $row->approved_at,
                'approved_by' => $action === 'approve' ? auth()->id() : $row->approved_by,
                'published_at' => $action === 'publish' ? now() : $row->published_at]);
        });
        return back()->with('success', 'Article '.$action.' action completed.');
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true)
            && ModuleAccess::allows(auth()->user(), ModuleAccess::TRADINGHUB), 403);
    }
}
