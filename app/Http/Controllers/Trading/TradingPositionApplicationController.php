<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralLinks;
use App\Models\TradingJournal;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TradingPositionApplicationController extends Controller
{
    public function index()
    {
        $this->ensureTradingMember();

        $user = auth()->user();
        $eligibility = $this->eligibility($user);
        $latestApplication = TradingPositionApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();
        $applications = TradingPositionApplication::where('user_id', $user->id)
            ->latest('id')
            ->get();
        $positions = $this->availablePositionsFor($user);
        $referralData = $this->referralData($user);

        return view('traders.positions.index', compact(
            'eligibility',
            'latestApplication',
            'applications',
            'positions',
            'referralData'
        ));
    }

    public function store(Request $request)
    {
        $this->ensureTradingMember();

        $user = auth()->user();
        $positions = array_keys($this->availablePositionsFor($user));

        $data = $request->validate([
            'requested_position' => ['required', Rule::in($positions)],
            'strategy_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'trade_history_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'personality_summary' => ['nullable', 'required_if:requested_position,leadership', 'string', 'max:5000'],
            'marketing_plan' => ['nullable', 'required_if:requested_position,recruiter', 'string', 'max:5000'],
            'client_support_plan' => ['required', 'string', 'max:5000'],
            'supporting_document' => ['nullable', 'file', 'mimes:pdf,doc,docx,jpg,jpeg,png', 'max:10240'],
        ]);

        $eligibility = $this->eligibility($user);
        if (! $eligibility['eligible']) {
            return back()->with([
                'message' => $this->eligibilityMessage($eligibility),
                'alert-type' => 'warning',
            ]);
        }

        $hasPending = TradingPositionApplication::where('user_id', $user->id)
            ->where('status', TradingPositionApplication::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return back()->with([
                'message' => 'You already have a trading position application pending review.',
                'alert-type' => 'info',
            ]);
        }

        $documentPath = null;
        if ($request->hasFile('supporting_document')) {
            $documentPath = $request->file('supporting_document')->store('trading_position_applications');
        }

        TradingPositionApplication::create([
            'user_id' => $user->id,
            'requested_position' => $data['requested_position'],
            'requested_role_id' => TradingPositionApplication::roleForPosition($data['requested_position']),
            'status' => TradingPositionApplication::STATUS_PENDING,
            'first_trade_date' => $eligibility['first_trade_date'],
            'trade_count_snapshot' => $eligibility['trade_count'],
            'strategy_summary' => $data['strategy_summary'] ?? null,
            'trade_history_summary' => $data['trade_history_summary'] ?? null,
            'personality_summary' => $data['personality_summary'] ?? null,
            'marketing_plan' => $data['marketing_plan'] ?? null,
            'client_support_plan' => $data['client_support_plan'],
            'supporting_document_path' => $documentPath,
            'submitted_at' => now(),
        ]);

        return redirect()->route('trading.positions.index')->with([
            'message' => 'Your trading position application has been submitted for administration review.',
            'alert-type' => 'success',
        ]);
    }

    public function adminIndex()
    {
        $this->ensureAdmin();

        $pendingApplications = TradingPositionApplication::with('applicant')
            ->where('status', TradingPositionApplication::STATUS_PENDING)
            ->latest('submitted_at')
            ->get();

        $reviewedApplications = TradingPositionApplication::with(['applicant', 'reviewer'])
            ->where('status', '!=', TradingPositionApplication::STATUS_PENDING)
            ->latest('reviewed_at')
            ->latest('id')
            ->take(80)
            ->get();

        $metrics = [
            'pending' => TradingPositionApplication::where('status', TradingPositionApplication::STATUS_PENDING)->count(),
            'approved' => TradingPositionApplication::where('status', TradingPositionApplication::STATUS_APPROVED)->count(),
            'rejected' => TradingPositionApplication::where('status', TradingPositionApplication::STATUS_REJECTED)->count(),
            'leaders' => User::where('role_id', TradingPositionApplication::ROLE_LEADERSHIP)->count(),
            'recruiters' => User::where('role_id', TradingPositionApplication::ROLE_RECRUITER)->count(),
        ];

        return view('admin.trading_positions.index', compact(
            'pendingApplications',
            'reviewedApplications',
            'metrics'
        ));
    }

    public function approve(Request $request, TradingPositionApplication $application)
    {
        $this->ensureAdmin();

        if (! $application->isPending()) {
            return back()->with([
                'message' => 'Only pending applications can be approved.',
                'alert-type' => 'warning',
            ]);
        }

        $data = $request->validate([
            'review_note' => ['nullable', 'string', 'max:3000'],
        ]);

        $application->update([
            'status' => TradingPositionApplication::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'] ?? 'Position approved by administration.',
        ]);

        $applicant = $application->applicant;
        if ($applicant) {
            $applicant->role_id = $application->requested_role_id;
            $applicant->status = 1;
            $applicant->save();

            $referralLink = ReferralLinks::ensureUniqueLink(
                (int) $application->requested_role_id,
                (int) $applicant->id,
                $applicant->referral_code
            );

            if ($applicant->referral_code !== $referralLink->referral_code) {
                $applicant->referral_code = $referralLink->referral_code;
                $applicant->save();
            }

            AppNotificationService::notifyUser(
                (int) $applicant->id,
                'Trading position approved',
                'Your ' . $application->requestedPositionLabel() . ' application has been approved.',
                route('trading.positions.index'),
                'verification'
            );
        }

        return back()->with([
            'message' => $application->requestedPositionLabel().' application approved. The member has been upgraded and referral links are active.',
            'alert-type' => 'success',
        ]);
    }

    public function reject(Request $request, TradingPositionApplication $application)
    {
        $this->ensureAdmin();

        if (! $application->isPending()) {
            return back()->with([
                'message' => 'Only pending applications can be rejected.',
                'alert-type' => 'warning',
            ]);
        }

        $data = $request->validate([
            'review_note' => ['required', 'string', 'max:3000'],
        ]);

        $application->update([
            'status' => TradingPositionApplication::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'review_note' => $data['review_note'],
        ]);

        if ($application->applicant) {
            AppNotificationService::notifyUser(
                (int) $application->applicant->id,
                'Trading position reviewed',
                'Your ' . $application->requestedPositionLabel() . ' application was rejected. Please review the admin note.',
                route('trading.positions.index'),
                'verification'
            );
        }

        return back()->with([
            'message' => 'Trading position application rejected and review note saved.',
            'alert-type' => 'warning',
        ]);
    }

    public function downloadDocument(TradingPositionApplication $application)
    {
        $this->ensureAdmin();

        abort_unless($application->supporting_document_path && Storage::exists($application->supporting_document_path), 404);

        $extension = pathinfo($application->supporting_document_path, PATHINFO_EXTENSION) ?: 'file';

        return Storage::download(
            $application->supporting_document_path,
            'trading-position-application-'.$application->id.'.'.$extension
        );
    }

    private function eligibility(User $user): array
    {
        $tradeQuery = TradingJournal::where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('type', 'trade')->orWhereNull('type');
            });

        $firstTrade = (clone $tradeQuery)->min('open_date');
        $tradeCount = (clone $tradeQuery)->count();
        $firstTradeDate = $firstTrade ? Carbon::parse($firstTrade)->startOfDay() : null;
        $eligibleFrom = $firstTradeDate?->copy()->addDays(30);
        $today = now()->startOfDay();
        $requiredTradeCount = 60;
        $hasRequiredTradeAge = $eligibleFrom !== null && $today->greaterThanOrEqualTo($eligibleFrom);
        $hasRequiredTradeCount = $tradeCount >= $requiredTradeCount;

        return [
            'eligible' => $hasRequiredTradeAge && $hasRequiredTradeCount,
            'first_trade_date' => $firstTradeDate?->toDateString(),
            'trade_count' => $tradeCount,
            'eligible_from' => $eligibleFrom?->toDateString(),
            'days_since_first_trade' => $firstTradeDate ? $firstTradeDate->diffInDays($today) : 0,
            'required_days' => 30,
            'required_trade_count' => $requiredTradeCount,
            'remaining_trade_count' => max(0, $requiredTradeCount - $tradeCount),
            'has_required_trade_age' => $hasRequiredTradeAge,
            'has_required_trade_count' => $hasRequiredTradeCount,
        ];
    }

    private function eligibilityMessage(array $eligibility): string
    {
        if (! $eligibility['has_required_trade_age'] && ! $eligibility['has_required_trade_count']) {
            return 'You can apply after your first trade is at least 30 days old and you have at least 60 trade records.';
        }

        if (! $eligibility['has_required_trade_age']) {
            return 'You can apply only after your first recorded trade open date is at least 30 days old.';
        }

        return 'You need at least 60 trade records before applying for a trading position.';
    }

    private function availablePositionsFor(User $user): array
    {
        $roleId = (int) $user->role_id;

        if ($roleId === TradingPositionApplication::ROLE_LEADERSHIP) {
            return [];
        }

        if ($roleId === TradingPositionApplication::ROLE_RECRUITER) {
            return [
                TradingPositionApplication::POSITION_LEADERSHIP => 'Leadership',
            ];
        }

        return [
            TradingPositionApplication::POSITION_RECRUITER => 'Recruiter',
            TradingPositionApplication::POSITION_LEADERSHIP => 'Leadership',
        ];
    }

    private function referralData(User $user): ?array
    {
        if (! $user->isTradingRecruiter()) {
            return null;
        }

        $referralLink = ReferralLinks::ensureUniqueLink(
            (int) $user->role_id,
            (int) $user->id,
            $user->referral_code
        );

        if ($user->referral_code !== $referralLink->referral_code) {
            $user->referral_code = $referralLink->referral_code;
            $user->save();
        }

        $webRegistrationUrl = Route::has('register')
            ? route('register', ['referral_code' => $referralLink->referral_code])
            : url('/register?referral_code='.$referralLink->referral_code);

        $brokerBaseUrl = config('services.broker.registration_url');
        $brokerRegistrationUrl = $brokerBaseUrl
            ? $brokerBaseUrl.(str_contains($brokerBaseUrl, '?') ? '&' : '?').'referral_code='.urlencode($referralLink->referral_code)
            : null;

        return [
            'referral_code' => $referralLink->referral_code,
            'web_registration_url' => $webRegistrationUrl,
            'broker_registration_url' => $brokerRegistrationUrl,
            'direct_downlines' => Referral::where('upline_user_id', $user->id)->count(),
        ];
    }

    private function ensureTradingMember(): void
    {
        abort_unless(auth()->check() && auth()->user()->isTradingMember(), 403);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }
}
