<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeBase;
use App\Support\ModuleAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AiKnowledgeBaseController extends Controller
{
    private const ADMIN_ROLES = [1, 2];

    public function index(Request $request)
    {
        $this->ensureAdmin();

        $query = AiKnowledgeBase::query()->latest();

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
        $statuses = AiKnowledgeBase::statuses();

        return view('admin.ai_knowledge_base.create', compact('modules', 'statuses'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        AiKnowledgeBase::create($this->validatedKnowledgeBase($request));

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record created successfully.');
    }

    public function edit(AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();

        $modules = AiKnowledgeBase::modules();
        $statuses = AiKnowledgeBase::statuses();
        $versions = DB::table('ai_knowledge_versions')->where('knowledge_id', $knowledgeBase->id)
            ->orderByDesc('version')->limit(10)->get();

        return view('admin.ai_knowledge_base.edit', compact('knowledgeBase', 'modules', 'statuses', 'versions'));
    }

    public function update(Request $request, AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();

        $data = $this->validatedKnowledgeBase($request);
        DB::transaction(function () use ($knowledgeBase, $data): void {
            $knowledgeBase = AiKnowledgeBase::lockForUpdate()->findOrFail($knowledgeBase->id);
            DB::table('ai_knowledge_versions')->insert([
                'knowledge_id' => $knowledgeBase->id, 'version' => $knowledgeBase->version,
                'title' => $knowledgeBase->title, 'content' => $knowledgeBase->content,
                'source' => $knowledgeBase->source, 'status' => $knowledgeBase->status,
                'changed_by' => auth()->id(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $knowledgeBase->update($data + ['version' => $knowledgeBase->version + 1]);
        });

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record updated successfully.');
    }

    public function destroy(AiKnowledgeBase $knowledgeBase)
    {
        $this->ensureAdmin();

        $knowledgeBase->update(['status' => AiKnowledgeBase::STATUS_INACTIVE]);

        return redirect()
            ->route('admin.ai_knowledge_base.index')
            ->with('success', 'AI knowledge base record unpublished.');
    }

    private function validatedKnowledgeBase(Request $request): array
    {
        return $request->validate([
            'module' => ['required', 'string', Rule::in(array_keys(AiKnowledgeBase::modules()))],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:20000'],
            'status' => ['required', 'string', Rule::in(array_keys(AiKnowledgeBase::statuses()))],
            'difficulty' => ['nullable', Rule::in(['beginner', 'intermediate', 'advanced'])],
            'source' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:12'],
            'reviewed_at' => ['nullable', 'date'],
        ]);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true)
            && ModuleAccess::allows(auth()->user(), ModuleAccess::TRADINGHUB), 403);
    }
}
