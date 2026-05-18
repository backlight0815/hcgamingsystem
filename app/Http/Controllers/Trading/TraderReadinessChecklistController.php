<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Capital;
use App\Models\KnowledgeCentre;
use App\Models\TraderOnboardingApplication;
use App\Models\TradingJournal;
use App\Models\TradingPositionApplication;
use App\Models\TradingRecording;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class TraderReadinessChecklistController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureTradingMember($request);

        $user = $request->user() ?: auth()->user();
        $items = DB::table('trader_readiness_checklist_items')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $progress = DB::table('trader_readiness_checklist_progress')
            ->where('user_id', $user->id)
            ->whereIn('item_id', $items->pluck('id'))
            ->get()
            ->keyBy('item_id');

        $completed = $progress
            ->filter(fn ($record): bool => $record->completed_at !== null)
            ->count();

        $coreItems = $items->where('is_core', 1);
        $coreCompleted = $coreItems
            ->filter(fn ($item): bool => optional($progress->get($item->id))->completed_at !== null)
            ->count();

        $total = max(1, $items->count());
        $percent = (int) round(($completed / $total) * 100);
        $corePercent = (int) round(($coreCompleted / max(1, $coreItems->count())) * 100);

        $categorySummaries = $items
            ->groupBy('category')
            ->map(function ($categoryItems) use ($progress): array {
                $total = $categoryItems->count();
                $done = $categoryItems
                    ->filter(fn ($item): bool => optional($progress->get($item->id))->completed_at !== null)
                    ->count();

                return [
                    'total' => $total,
                    'completed' => $done,
                    'percent' => (int) round(($done / max(1, $total)) * 100),
                ];
            });

        $latestOnboarding = TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();

        $depositTotal = (float) Capital::where('user_id', $user->id)
            ->where('type', 1)
            ->sum('amount');

        $tradeCount = TradingJournal::where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            })
            ->count();

        $context = [
            'verification_label' => $latestOnboarding?->statusLabel() ?? 'Not Submitted',
            'verification_tone' => $latestOnboarding?->statusTone() ?? 'secondary',
            'has_deposit' => $depositTotal > 0 || (bool) ($latestOnboarding?->has_deposit),
            'deposit_total' => $depositTotal,
            'trade_count' => $tradeCount,
            'knowledge_count' => KnowledgeCentre::where('status', true)->where('approval_status', 'approved')->count(),
            'recording_count' => TradingRecording::where('status', true)->where('approval_status', 'approved')->count(),
            'last_completed_at' => $progress->max('completed_at'),
        ];

        $stage = $this->readinessStage($percent);

        return view('traders.readiness_checklist.index', [
            'items' => $items,
            'progress' => $progress,
            'completed' => $completed,
            'total' => $items->count(),
            'percent' => $percent,
            'corePercent' => $corePercent,
            'categorySummaries' => $categorySummaries,
            'context' => $context,
            'stage' => $stage,
            'routeResolver' => fn (?string $routeName): ?string => $this->resourceUrl($routeName),
        ]);
    }

    public function update(Request $request, int $item): RedirectResponse
    {
        $this->ensureTradingMember($request);

        $checklistItem = DB::table('trader_readiness_checklist_items')
            ->where('id', $item)
            ->where('is_active', 1)
            ->first();

        abort_unless($checklistItem, 404);

        $data = $request->validate([
            'status' => ['required', 'in:complete,reopen'],
            'self_rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            'demo_practiced' => ['nullable', 'boolean'],
            'reflection_note' => ['nullable', 'string', 'max:1200'],
        ]);

        if ($data['status'] === 'reopen') {
            DB::table('trader_readiness_checklist_progress')
                ->where('user_id', ($request->user() ?: auth()->user())->id)
                ->where('item_id', $checklistItem->id)
                ->delete();

            return back()->with([
                'message' => 'Checklist item reopened.',
                'alert-type' => 'info',
            ]);
        }

        DB::table('trader_readiness_checklist_progress')->updateOrInsert(
            [
                'user_id' => ($request->user() ?: auth()->user())->id,
                'item_id' => $checklistItem->id,
            ],
            [
                'completed_at' => now(),
                'self_rating' => $data['self_rating'] ?? null,
                'demo_practiced' => $request->boolean('demo_practiced'),
                'reflection_note' => $data['reflection_note'] ?? null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return back()->with([
            'message' => 'Checklist item completed.',
            'alert-type' => 'success',
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->ensureTradingMember($request);

        DB::table('trader_readiness_checklist_progress')
            ->where('user_id', ($request->user() ?: auth()->user())->id)
            ->delete();

        return back()->with([
            'message' => 'Your readiness checklist has been reset.',
            'alert-type' => 'info',
        ]);
    }

    private function ensureTradingMember(Request $request): void
    {
        $user = $request->user() ?: auth()->user();
        $roleId = (int) ($user?->role_id ?? 0);

        abort_unless(
            in_array($roleId, array_merge([1, 2], TradingPositionApplication::tradingMemberRoles()), true),
            403
        );
    }

    private function resourceUrl(?string $routeName): ?string
    {
        if (! $routeName || ! Route::has($routeName)) {
            return null;
        }

        return route($routeName);
    }

    private function readinessStage(int $percent): array
    {
        return match (true) {
            $percent >= 100 => [
                'label' => 'Checklist Complete',
                'tone' => 'success',
                'message' => 'Demo discipline and preparation are complete. Start live only with reduced risk and continued journaling.',
            ],
            $percent >= 80 => [
                'label' => 'Final Review',
                'tone' => 'primary',
                'message' => 'Most foundations are complete. Review risk size, stop rules, and recent demo trades before any live exposure.',
            ],
            $percent >= 50 => [
                'label' => 'Practice Phase',
                'tone' => 'warning',
                'message' => 'Continue on demo and keep journaling. Do not rush live entries just because the account is funded.',
            ],
            default => [
                'label' => 'Foundation Stage',
                'tone' => 'danger',
                'message' => 'Stay on demo. Build the basics first: verification, materials, risk, technical levels, news, and order execution.',
            ],
        };
    }
}
