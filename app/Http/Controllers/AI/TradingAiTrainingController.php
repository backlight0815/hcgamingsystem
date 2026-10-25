<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Models\AiKnowledgeBase;
use App\Services\TradingHubAssistantService;
use App\Support\ModuleAccess;
use App\Support\TradingAiSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingAiTrainingController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isBackofficeAdministrator()
            && ModuleAccess::allows($request->user(), ModuleAccess::TRADINGHUB), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $tab = in_array($request->query('tab'), ['dashboard', 'questions', 'unanswered', 'history', 'analytics', 'test'], true)
            ? $request->query('tab') : 'dashboard';
        $entries = AiKnowledgeBase::query()->where('entry_type', 'faq')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = mb_substr(trim((string) $request->query('search')), 0, 100);
                $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('content', 'like', '%'.$term.'%'));
            })->when(in_array($request->query('status'), array_keys($this->statuses()), true),
                fn ($query) => $query->where('status', $request->query('status')))
            ->when(in_array($request->query('language'), ['en', 'zh'], true),
                fn ($query) => $query->where('language', $request->query('language')))
            ->orderByDesc('updated_at')->paginate(15, ['*'], 'questions_page')->withQueryString();
        $unanswered = DB::table('ai_unanswered_questions as u')
            ->leftJoin('ai_knowledge_bases as k', 'k.id', '=', 'u.training_id')
            ->select('u.*', 'k.status as training_status')
            ->when($request->filled('search'), function ($query) use ($request): void {
                $query->where('u.question', 'like', '%'.mb_substr(trim((string) $request->query('search')), 0, 100).'%');
            })->when(in_array($request->query('queue_status'), ['pending', 'training', 'resolved', 'archived'], true),
                fn ($query) => $query->where('u.status', $request->query('queue_status')))
            ->orderByDesc($request->query('sort') === 'frequency' ? 'u.frequency' : 'u.last_asked_at')
            ->paginate(15, ['*'], 'unanswered_page')->withQueryString();
        $history = DB::table('ai_knowledge_versions as v')
            ->join('ai_knowledge_bases as k', 'k.id', '=', 'v.knowledge_id')
            ->where('k.entry_type', 'faq')->select('v.*', 'k.title as current_title')
            ->orderByDesc('v.id')->paginate(15, ['*'], 'history_page')->withQueryString();
        $metrics = $this->metrics($request);
        $topTopics = DB::table('ai_unanswered_questions')->select('category')
            ->selectRaw('SUM(frequency) as questions')
            ->groupBy('category')->orderByDesc('questions')->limit(5)->get();
        return view('admin.ai_knowledge_base.training', [
            'tab' => $tab, 'entries' => $entries, 'unanswered' => $unanswered,
            'history' => $history, 'metrics' => $metrics, 'topTopics' => $topTopics,
            'statuses' => $this->statuses(),
            'modules' => AiKnowledgeBase::modules(), 'settings' => TradingAiSettings::get(),
        ]);
    }

    public function create(Request $request)
    {
        $this->authorizeAdmin($request);
        $sourceQuestion = null;
        if ($request->filled('unanswered_id')) {
            $sourceQuestion = DB::table('ai_unanswered_questions')->where('id', $request->integer('unanswered_id'))
                ->where('status', '!=', 'archived')->first();
        }
        return view('admin.ai_knowledge_base.training_form', [
            'entry' => null, 'sourceQuestion' => $sourceQuestion,
            'modules' => AiKnowledgeBase::modules(), 'statuses' => $this->statuses(), 'versions' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $this->validatedEntry($request);
        $entry = AiKnowledgeBase::create($data + [
            'entry_type' => 'faq', 'status' => AiKnowledgeBase::STATUS_DRAFT,
            'source' => 'HC TradingHub administrator', 'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        if ($request->filled('unanswered_id')) {
            DB::table('ai_unanswered_questions')->where('id', $request->integer('unanswered_id'))
                ->where('status', 'pending')->update(['training_id' => $entry->id, 'status' => 'training', 'updated_at' => now()]);
        }
        return redirect()->route('admin.ai_training.edit', $entry->id)
            ->with('success', 'Training question #'.$entry->id.' saved as draft.');
    }

    public function edit(Request $request, AiKnowledgeBase $entry)
    {
        $this->authorizeAdmin($request);
        $this->faq($entry);
        $versions = DB::table('ai_knowledge_versions')->where('knowledge_id', $entry->id)
            ->orderByDesc('version')->paginate(10);
        return view('admin.ai_knowledge_base.training_form', [
            'entry' => $entry, 'sourceQuestion' => null, 'modules' => AiKnowledgeBase::modules(),
            'statuses' => $this->statuses(), 'versions' => $versions,
        ]);
    }

    public function update(Request $request, AiKnowledgeBase $entry)
    {
        $this->authorizeAdmin($request);
        $this->faq($entry);
        $data = $this->validatedEntry($request, $entry->id);
        DB::transaction(function () use ($entry, $data, $request): void {
            $locked = AiKnowledgeBase::lockForUpdate()->findOrFail($entry->id);
            $this->snapshot($locked, $request->user()->id);
            $locked->update($data + [
                'status' => AiKnowledgeBase::STATUS_DRAFT, 'approved_at' => null,
                'approved_by' => null, 'published_at' => null,
                'updated_by' => $request->user()->id, 'version' => $locked->version + 1,
            ]);
            DB::table('ai_unanswered_questions')->where('training_id', $locked->id)
                ->update(['status' => 'training', 'updated_at' => now()]);
        });
        return redirect()->route('admin.ai_training.edit', $entry->id)
            ->with('success', 'Changes saved as draft. Submit for review before publishing.');
    }

    public function transition(Request $request, AiKnowledgeBase $entry)
    {
        $this->authorizeAdmin($request);
        $this->faq($entry);
        $action = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'publish', 'archive'])]])['action'];
        $allowed = [
            'submit' => [AiKnowledgeBase::STATUS_DRAFT],
            'approve' => [AiKnowledgeBase::STATUS_PENDING_REVIEW],
            'publish' => [AiKnowledgeBase::STATUS_APPROVED],
            'archive' => [AiKnowledgeBase::STATUS_DRAFT, AiKnowledgeBase::STATUS_PENDING_REVIEW,
                AiKnowledgeBase::STATUS_APPROVED, AiKnowledgeBase::STATUS_PUBLISHED],
        ];
        DB::transaction(function () use ($entry, $action, $allowed, $request): void {
            $locked = AiKnowledgeBase::lockForUpdate()->findOrFail($entry->id);
            if (! in_array($locked->status, $allowed[$action], true)) {
                throw ValidationException::withMessages(['action' => 'This status transition is not available.']);
            }
            $this->snapshot($locked, $request->user()->id);
            $changes = ['status' => match ($action) {
                'submit' => AiKnowledgeBase::STATUS_PENDING_REVIEW,
                'approve' => AiKnowledgeBase::STATUS_APPROVED,
                'publish' => AiKnowledgeBase::STATUS_PUBLISHED,
                'archive' => AiKnowledgeBase::STATUS_ARCHIVED,
            }, 'updated_by' => $request->user()->id, 'version' => $locked->version + 1];
            if ($action === 'approve') $changes += ['approved_by' => $request->user()->id, 'approved_at' => now()];
            if ($action === 'publish') {
                if (! $locked->approved_at) throw ValidationException::withMessages(['action' => 'Approve this answer before publishing.']);
                $changes['published_at'] = now();
            }
            $locked->update($changes);
            if ($action === 'publish') {
                $hashes = array_map(fn ($phrase) => hash('sha256', $phrase),
                    array_merge([$locked->normalized_question], $locked->alternative_normalized ?? []));
                DB::table('ai_unanswered_questions')->where(function ($query) use ($locked, $hashes): void {
                    $query->where('training_id', $locked->id)->orWhereIn('question_hash', $hashes);
                })->update(['training_id' => $locked->id, 'status' => 'resolved', 'updated_at' => now()]);
            }
        });
        return back()->with('success', 'Training entry '.$action.' action completed.');
    }

    public function duplicate(Request $request, AiKnowledgeBase $entry)
    {
        $this->authorizeAdmin($request);
        $this->faq($entry);
        $copy = $entry->replicate();
        $copy->title = 'Copy of '.$entry->title.' '.substr((string) \Illuminate\Support\Str::uuid(), 0, 6);
        $copy->normalized_question = AiKnowledgeBase::normalizeQuestion($copy->title);
        $copy->alternative_questions = [];
        $copy->alternative_normalized = [];
        $copy->status = AiKnowledgeBase::STATUS_DRAFT;
        $copy->approved_at = $copy->published_at = $copy->last_tested_at = null;
        $copy->approved_by = null;
        $copy->created_by = $copy->updated_by = $request->user()->id;
        $copy->version = 1;
        $copy->save();
        return redirect()->route('admin.ai_training.edit', $copy->id)->with('success', 'Draft copy created.');
    }

    public function rollback(Request $request, AiKnowledgeBase $entry, int $version)
    {
        $this->authorizeAdmin($request);
        $this->faq($entry);
        DB::transaction(function () use ($entry, $version, $request): void {
            $locked = AiKnowledgeBase::lockForUpdate()->findOrFail($entry->id);
            $old = DB::table('ai_knowledge_versions')->where('knowledge_id', $entry->id)
                ->where('version', $version)->first();
            abort_unless($old, 404);
            $normalized = AiKnowledgeBase::normalizeQuestion($old->title);
            $metadata = json_decode($old->metadata ?? '{}', true) ?: [];
            if (AiKnowledgeBase::where('entry_type', 'faq')->where('language', $metadata['language'] ?? $locked->language)
                ->where('normalized_question', $normalized)->where('id', '!=', $locked->id)->exists()) {
                throw ValidationException::withMessages(['version' => 'The earlier question now conflicts with another training entry.']);
            }
            $this->snapshot($locked, $request->user()->id);
            $locked->update([
                'title' => $old->title, 'normalized_question' => $normalized,
                'content' => $old->content, 'source' => $old->source,
                'alternative_questions' => $metadata['alternative_questions'] ?? $locked->alternative_questions,
                'alternative_normalized' => $metadata['alternative_normalized'] ?? $locked->alternative_normalized,
                'keywords' => $metadata['keywords'] ?? $locked->keywords,
                'module' => $metadata['module'] ?? $locked->module,
                'language' => $metadata['language'] ?? $locked->language,
                'priority' => $metadata['priority'] ?? $locked->priority,
                'effective_at' => $metadata['effective_at'] ?? $locked->effective_at,
                'status' => AiKnowledgeBase::STATUS_DRAFT, 'approved_at' => null,
                'approved_by' => null, 'published_at' => null,
                'updated_by' => $request->user()->id, 'version' => $locked->version + 1,
            ]);
        });
        return redirect()->route('admin.ai_training.edit', $entry->id)
            ->with('success', 'Earlier content restored as a new draft version.');
    }

    public function archiveUnanswered(Request $request, int $question)
    {
        $this->authorizeAdmin($request);
        DB::table('ai_unanswered_questions')->where('id', $question)
            ->update(['status' => 'archived', 'updated_at' => now()]);
        return back()->with('success', 'Question archived.');
    }

    public function test(Request $request, TradingHubAssistantService $assistant): JsonResponse
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['question' => ['required', 'string', 'min:2', 'max:2000'],
            'language' => ['required', Rule::in(['en', 'zh'])]]);
        $settings = TradingAiSettings::get();
        $result = $assistant->answer($data['question'], $request->user(), [
            'language' => $data['language'], 'maximum_risk_percent' => $settings['maximum_risk_percent'],
            'preferred_rr' => $settings['minimum_rr'],
        ]);
        if (($result['intent'] ?? null) === 'approved_faq') {
            AiKnowledgeBase::query()->where('entry_type', 'faq')
                ->whereKey(data_get($result, 'sources.0.id'))->update(['last_tested_at' => now()]);
        }
        return response()->json($result);
    }

    public function export(Request $request)
    {
        $this->authorizeAdmin($request);
        $type = $request->query('type') === 'analytics' ? 'analytics' : 'unanswered';
        return response()->streamDownload(function () use ($type, $request): void {
            $out = fopen('php://output', 'w');
            if ($type === 'analytics') {
                fputcsv($out, ['Metric', 'Value']);
                foreach ($this->metrics($request) as $name => $value) fputcsv($out, [$name, $value]);
            } else {
                fputcsv($out, ['Question ID', 'Question', 'Frequency', 'Last asked (Malaysia)', 'Status', 'Training ID']);
                DB::table('ai_unanswered_questions')->orderBy('id')->chunk(200, function ($rows) use ($out): void {
                    foreach ($rows as $row) fputcsv($out, [$row->id, $this->safeCsv($row->question), $row->frequency,
                        \Carbon\Carbon::parse($row->last_asked_at)->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i:s'),
                        $row->status, $row->training_id]);
                });
            }
            fclose($out);
        }, 'tradinghub-ai-'.$type.'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function validatedEntry(Request $request, ?int $exceptId = null): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'min:3', 'max:255'],
            'answer' => ['required', 'string', 'min:5', 'max:20000'],
            'alternative_questions' => ['nullable', 'string', 'max:4000'],
            'keywords' => ['nullable', 'string', 'max:500'],
            'module' => ['required', Rule::in(array_keys(AiKnowledgeBase::modules()))],
            'language' => ['required', Rule::in(['en', 'zh'])],
            'priority' => ['required', 'integer', 'min:0', 'max:100'],
            'effective_at' => ['nullable', 'date'],
        ]);
        $alternatives = collect(preg_split('/\r?\n/', $data['alternative_questions'] ?? '') ?: [])
            ->map(fn ($line) => trim($line))->filter()->unique()->take(20)->values()->all();
        foreach ($alternatives as $alternative) {
            if (mb_strlen($alternative) > 255) throw ValidationException::withMessages(['alternative_questions' => 'Each alternative question must be at most 255 characters.']);
        }
        $normalized = AiKnowledgeBase::normalizeQuestion($data['question']);
        $normalizedAlternatives = array_map([AiKnowledgeBase::class, 'normalizeQuestion'], $alternatives);
        if ($normalized === '' || in_array('', $normalizedAlternatives, true)
            || in_array($normalized, $normalizedAlternatives, true)
            || count($normalizedAlternatives) !== count(array_unique($normalizedAlternatives))) {
            throw ValidationException::withMessages(['question' => 'The question and its alternatives must be distinct and meaningful.']);
        }
        $all = array_merge([$normalized], $normalizedAlternatives);
        $conflict = AiKnowledgeBase::query()->where('entry_type', 'faq')->where('language', $data['language'])
            ->when($exceptId, fn ($query) => $query->where('id', '!=', $exceptId))
            ->where(function ($query) use ($all): void {
                $query->whereIn('normalized_question', $all);
                foreach ($all as $item) $query->orWhereJsonContains('alternative_normalized', $item);
            })->exists();
        if ($conflict) throw ValidationException::withMessages(['question' => 'This question or one of its alternatives already exists.']);
        return [
            'title' => trim($data['question']), 'normalized_question' => $normalized,
            'content' => trim($data['answer']), 'module' => $data['module'],
            'language' => $data['language'], 'priority' => (int) $data['priority'],
            'effective_at' => isset($data['effective_at'])
                ? \Carbon\Carbon::parse($data['effective_at'], 'Asia/Kuala_Lumpur')->utc()->toDateTimeString() : null,
            'alternative_questions' => $alternatives, 'alternative_normalized' => $normalizedAlternatives,
            'keywords' => collect(explode(',', $data['keywords'] ?? ''))->map(fn ($x) => trim($x))->filter()->unique()->take(20)->values()->all(),
        ];
    }

    private function snapshot(AiKnowledgeBase $entry, int $userId): void
    {
        DB::table('ai_knowledge_versions')->insert([
            'knowledge_id' => $entry->id, 'version' => $entry->version,
            'title' => $entry->title, 'content' => $entry->content,
            'source' => $entry->source, 'status' => $entry->status,
            'metadata' => json_encode(['alternative_questions' => $entry->alternative_questions,
                'alternative_normalized' => $entry->alternative_normalized, 'keywords' => $entry->keywords,
                'module' => $entry->module, 'language' => $entry->language,
                'priority' => $entry->priority, 'effective_at' => $entry->effective_at?->toIso8601String()]),
            'changed_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function metrics(Request $request): array
    {
        $range = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date', 'after_or_equal:from']]);
        $from = isset($range['from']) ? \Carbon\Carbon::parse($range['from'], 'Asia/Kuala_Lumpur')->startOfDay()->utc() : null;
        $to = isset($range['to']) ? \Carbon\Carbon::parse($range['to'], 'Asia/Kuala_Lumpur')->endOfDay()->utc() : null;
        $messages = fn ($role) => DB::table('ai_chat_messages')->where('role', $role)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
        $usage = DB::table('ai_chat_usage')->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));
        return [
            'total_questions' => $messages('user')->count(),
            'answered' => $messages('user')->where('status', 'answered')->count(),
            'unanswered' => $messages('user')->where('status', 'unanswered')->count(),
            'failed' => $messages('user')->where('status', 'failed')->count(),
            'faq_usage' => $messages('assistant')->where('response_source', 'approved_faq')->count(),
            'knowledge_usage' => $messages('assistant')->where('response_source', 'knowledge_base')->count(),
            'external_usage' => $messages('assistant')->where('response_source', 'external_ai')->count(),
            'provider_failures' => (clone $usage)->whereNotNull('provider_failure_code')->where('provider_failure_code', '!=', 'provider_disabled')->count(),
            'average_response_ms' => (int) round((float) $messages('assistant')->avg('response_time_ms')),
            'training_entries' => AiKnowledgeBase::where('entry_type', 'faq')->count(),
            'published_entries' => AiKnowledgeBase::where('entry_type', 'faq')->where('status', 'published')->count(),
        ];
    }

    private function safeCsv(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'".$value : $value;
    }

    private function faq(AiKnowledgeBase $entry): void
    {
        abort_unless($entry->entry_type === 'faq', 404);
    }

    private function statuses(): array
    {
        return ['draft' => 'Draft', 'pending_review' => 'Pending review',
            'approved' => 'Approved', 'published' => 'Published', 'archived' => 'Archived'];
    }
}
