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
                ->select('ai_chat_feedback.rating', 'ai_chat_feedback.comment', 'ai_chat_feedback.created_at', 'ai_chat_messages.content')
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
        ]);
        foreach ($data as $key => $value) {
            DB::table('ai_chat_settings')->updateOrInsert(['key' => $key], [
                'value' => (string) $value, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return redirect()->route('admin.ai_settings.index')->with('success', 'TradingHub AI settings updated.');
    }
}
