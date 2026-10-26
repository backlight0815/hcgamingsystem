<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Support\ModuleAccess;
use App\Support\TradingAiPrompt;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TradingAiPromptController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isBackofficeAdministrator()
            && ModuleAccess::allows($request->user(), ModuleAccess::TRADINGHUB), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        return view('admin.ai_knowledge_base.prompts', [
            'current' => TradingAiPrompt::current(),
            'versions' => DB::table('ai_chat_prompts')->orderByDesc('version')->paginate(12),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['content' => ['required', 'string', 'min:1000', 'max:20000']]);
        DB::transaction(function () use ($data, $request): void {
            $latest = DB::table('ai_chat_prompts')->orderByDesc('version')->lockForUpdate()->first();
            DB::table('ai_chat_prompts')->insert([
                'version' => max(3, (int) ($latest->version ?? 0)) + 1,
                'content' => trim($data['content']), 'status' => 'draft',
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
        return redirect()->route('admin.ai_prompts.index')->with('success', 'A new prompt draft was saved. The published policy is unchanged.');
    }

    public function transition(Request $request, int $prompt)
    {
        $this->authorizeAdmin($request);
        $action = $request->validate(['action' => ['required', Rule::in(['submit', 'approve', 'publish', 'archive'])]])['action'];
        DB::transaction(function () use ($prompt, $action, $request): void {
            $row = DB::table('ai_chat_prompts')->where('id', $prompt)->lockForUpdate()->first();
            abort_unless($row, 404);
            $allowed = [
                'submit' => ['draft'], 'approve' => ['pending_review'],
                'publish' => ['approved'], 'archive' => ['draft', 'pending_review', 'approved'],
            ];
            if (! in_array($row->status, $allowed[$action], true)) {
                throw ValidationException::withMessages(['action' => 'This prompt status transition is unavailable.']);
            }
            $newStatus = match ($action) {
                'submit' => 'pending_review', 'approve' => 'approved',
                'publish' => 'published', 'archive' => 'archived',
            };
            if ($action === 'publish') {
                if (! $row->approved_at) throw ValidationException::withMessages(['action' => 'Human approval is required.']);
                DB::table('ai_chat_prompts')->where('status', 'published')
                    ->update(['status' => 'archived', 'updated_at' => now()]);
            }
            DB::table('ai_chat_prompts')->where('id', $row->id)->update([
                'status' => $newStatus,
                'approved_by' => $action === 'approve' ? $request->user()->id : $row->approved_by,
                'approved_at' => $action === 'approve' ? now() : $row->approved_at,
                'published_at' => $action === 'publish' ? now() : $row->published_at,
                'updated_at' => now(),
            ]);
        });
        return back()->with('success', 'Prompt '.$action.' action completed.');
    }
}
