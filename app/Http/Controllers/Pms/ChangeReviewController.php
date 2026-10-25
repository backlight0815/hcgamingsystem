<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Services\PmsChangeReviewService;
use App\Support\PmsAccess;
use App\Support\PmsReviewStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ChangeReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('pm_change_requests as reviews')
            ->join('users as submitters', 'submitters.id', '=', 'reviews.submitted_by')
            ->join('users as reviewers', 'reviewers.id', '=', 'reviews.reviewer_id')
            ->join('pm_projects as projects', 'projects.id', '=', 'reviews.project_id')
            ->select('reviews.*', 'submitters.name as submitter_name', 'reviewers.name as reviewer_name',
                'projects.key as project_key');
        if (! PmsAccess::administrator($request->user())) {
            $query->where(fn ($q) => $q->where('reviews.submitted_by', $request->user()->id)
                ->orWhere('reviews.reviewer_id', $request->user()->id));
        }
        if (in_array($request->input('status'), ['pending', 'approved', 'rejected', 'cancelled'], true)) {
            $code = match ($request->input('status')) {
                'pending' => PmsReviewStatus::PENDING, 'approved' => PmsReviewStatus::APPROVED,
                'rejected' => PmsReviewStatus::REJECTED, default => PmsReviewStatus::CANCELLED,
            };
            $query->where('reviews.status_code', $code);
        }
        $reviews = $query->orderByDesc('reviews.id')->paginate(20)->withQueryString();
        return $request->expectsJson() ? response()->json($reviews)
            : view('pms.change-reviews', compact('reviews'));
    }

    public function decide(Request $request, int $review, PmsChangeReviewService $service)
    {
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'comment' => 'required|string|min:3|max:10000',
        ]);
        $result = $service->decide($request, $review, $data['decision'], $data['comment']);
        return $request->expectsJson() ? response()->json($result)
            : redirect()->route('pms.change-reviews.index')->with('success', 'Review decision recorded.');
    }

    public function cancel(Request $request, int $review, PmsChangeReviewService $service)
    {
        $result = $service->cancel($request, $review);
        return $request->expectsJson() ? response()->json($result)
            : redirect()->route('pms.change-reviews.index')->with('success', 'Review request cancelled.');
    }
}
