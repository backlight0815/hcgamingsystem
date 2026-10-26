<?php

namespace App\Http\Controllers\AI;

use App\Http\Controllers\Controller;
use App\Support\ModuleAccess;
use App\Support\TradingAiSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradingAiSettingsController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->isBackofficeAdministrator()
            && ModuleAccess::allows($request->user(), ModuleAccess::TRADINGHUB), 403);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        return view('admin.ai_knowledge_base.settings', [
            'settings' => TradingAiSettings::get(),
            'models' => config('services.gemini.allowed_models', []),
            'feedback' => DB::table('ai_chat_feedback')->join('ai_chat_messages', 'ai_chat_messages.id', '=', 'ai_chat_feedback.message_id')
                ->select('ai_chat_feedback.id', 'ai_chat_feedback.rating', 'ai_chat_feedback.issue_category',
                    'ai_chat_feedback.comment', 'ai_chat_feedback.created_at', 'ai_chat_feedback.reviewed_at',
                    'ai_chat_feedback.resolution', 'ai_chat_messages.content', 'ai_chat_messages.prompt_version',
                    'ai_chat_messages.claim_evidence', 'ai_chat_messages.sources', 'ai_chat_messages.model')
                ->orderByDesc('ai_chat_feedback.id')->paginate(20),
        ]);
    }

    public function update(Request $request)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate([
            'default_risk_percent' => ['required', 'numeric', 'min:0.01', 'max:1'],
            'maximum_risk_percent' => ['required', 'numeric', 'min:0.1', 'max:1', 'gte:default_risk_percent'],
            'minimum_rr' => ['required', 'numeric', 'min:1', 'max:5'],
            'daily_request_limit' => ['required', 'integer', 'min:1', 'max:1000'],
            'retrieval_limit' => ['required', 'integer', 'min:1', 'max:8'],
            'retention_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'model' => ['required', Rule::in(config('services.gemini.allowed_models', []))],
            'operation_mode' => ['sometimes', 'required', Rule::in(['knowledge_only', 'hybrid', 'generative'])],
            'external_enabled' => ['sometimes', 'required', 'boolean'],
            'ai_name' => ['sometimes', 'required', 'string', 'max:100'],
            'ai_role' => ['sometimes', 'required', 'string', 'max:200'],
            'tone' => ['sometimes', 'required', Rule::in(['professional', 'friendly', 'educational', 'technical'])],
            'response_length' => ['sometimes', 'required', Rule::in(['short', 'balanced', 'detailed'])],
            'language_fallback' => ['sometimes', 'required', Rule::in(['en', 'none'])],
            'response_rules' => ['sometimes', 'required', 'string', 'max:4000'],
            'journal_mode_enabled' => ['sometimes', 'required', 'boolean'],
        ]);
        foreach ($data as $key => $value) {
            $table = in_array($key, ['operation_mode', 'external_enabled', 'ai_name', 'ai_role', 'tone',
                'response_length', 'language_fallback', 'response_rules', 'journal_mode_enabled'], true)
                ? 'ai_chat_response_settings' : 'ai_chat_settings';
            DB::table($table)->updateOrInsert(['key' => $key], [
                'value' => (string) $value, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return redirect()->route('admin.ai_settings.index')->with('success', 'TradingHub AI settings updated.');
    }

    public function resolveFeedback(Request $request, int $feedback)
    {
        $this->authorizeAdmin($request);
        $data = $request->validate(['resolution' => ['required', 'string', 'min:3', 'max:2000']]);
        $updated = DB::table('ai_chat_feedback')->where('id', $feedback)->update([
            'resolution' => $data['resolution'], 'reviewed_at' => now(),
            'reviewer_id' => $request->user()->id, 'updated_at' => now(),
        ]);
        abort_unless($updated, 404);
        return back()->with('success', 'Feedback review recorded. Historical answers were not changed.');
    }
}
