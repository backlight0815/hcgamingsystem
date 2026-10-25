<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\MarketOutlookPublicationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

class MarketOutlookPublicationController extends Controller
{
    public function index(Request $request, MarketOutlookPublicationService $outlooks)
    {
        $request->validate(['market' => ['sometimes', 'string', 'max:50'], 'q' => ['sometimes', 'string', 'max:100']]);
        $viewer = $this->viewer($request);
        $hasHistoryAccess = $outlooks->hasHistoryAccess($viewer);
        $query = $outlooks->visible($viewer, $hasHistoryAccess);
        if ($request->filled('market')) {
            $query->where('market', $request->input('market'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->input('q'));
            $query->where(fn ($builder) => $builder->where('title', 'like', "%{$term}%")
                ->orWhere('market_overview', 'like', "%{$term}%"));
        }
        $items = $hasHistoryAccess
            ? $query->paginate(12)->withQueryString()
            : $query->get();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'data' => $items instanceof LengthAwarePaginator ? $items->items() : $items,
                'meta' => [
                    'current_page' => $items instanceof LengthAwarePaginator ? $items->currentPage() : 1,
                    'last_page' => $items instanceof LengthAwarePaginator ? $items->lastPage() : 1,
                    'total' => $items instanceof LengthAwarePaginator ? $items->total() : $items->count(),
                    'has_history_access' => $hasHistoryAccess,
                ],
            ])->header('Cache-Control', 'private, no-store');
        }

        return response()->view('experiences.trading.outlooks.index', compact('items'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, int $analysis, MarketOutlookPublicationService $outlooks)
    {
        $viewer = $this->viewer($request);
        $item = $outlooks->visible($viewer)->find($analysis);
        if (! $item) {
            if (! $request->expectsJson() && ! $request->is('api/*') && $outlooks->published()->whereKey($analysis)->exists()) {
                return response()->view('experiences.trading.outlooks.restricted', [], 403)
                    ->header('Cache-Control', 'private, no-store');
            }
            abort(404);
        }
        $related = $outlooks->visible($viewer)->where('market_analyses.id', '!=', $item->id)
            ->where('market', $item->market)->limit(3)->get();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['data' => $item, 'related' => $related])
                ->header('Cache-Control', 'private, no-store');
        }

        return response()->view('experiences.trading.outlooks.show', compact('item', 'related'))
            ->header('Cache-Control', 'private, no-store');
    }

    private function viewer(Request $request): ?User
    {
        return $request->is('api/*') ? (Auth::guard('sanctum')->user() ?? $request->user()) : $request->user();
    }
}
