<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TraderOnboardingApplication;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class TraderOnboardingController extends Controller
{
    public function show()
    {
        $this->ensureTrader();

        $user = auth()->user();
        $latestApplication = TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();
        $applications = TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->get();
        $canSubmit = ! $latestApplication
            || $latestApplication->canResubmit()
            || $latestApplication->canStartNewApplication();

        if ($latestApplication && $latestApplication->isPending()) {
            $canSubmit = false;
        }

        if ($latestApplication && $latestApplication->isHardClosed()) {
            $canSubmit = false;
        }

        return view('traders.onboarding.show', compact(
            'latestApplication',
            'applications',
            'canSubmit'
        ));
    }

    public function store(Request $request)
    {
        $this->ensureTrader();

        $user = auth()->user();
        $latestApplication = TraderOnboardingApplication::where('user_id', $user->id)
            ->latest('id')
            ->first();

        if ($latestApplication && $latestApplication->isApproved()) {
            return redirect()
                ->route('all.statistics')
                ->with([
                    'message' => 'Your trader verification is already approved.',
                    'alert-type' => 'success',
                ]);
        }

        if ($latestApplication && $latestApplication->isPending()) {
            return back()->with([
                'message' => 'Your application is already waiting for administration review.',
                'alert-type' => 'info',
            ]);
        }

        if ($latestApplication && $latestApplication->isHardClosed()) {
            return back()->with([
                'message' => 'This application is closed. Please contact the HC person in charge to know more.',
                'alert-type' => 'error',
            ]);
        }

        $isDocumentResubmission = $latestApplication && $latestApplication->canResubmit();

        $data = $request->validate([
            'is_client' => ['required', 'boolean'],
            'has_deposit' => ['required', 'boolean'],
            'deposit_amount' => ['nullable', 'required_if:has_deposit,1', 'numeric', 'min:0'],
            'discord_username' => ['required', 'string', 'max:100'],
            'broker_uid' => ['required', 'string', 'max:100'],
            'broker_email' => ['required', 'email', 'max:255'],
            'document' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'trader_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($isDocumentResubmission) {
            $data['is_client'] = (bool) $latestApplication->is_client;
            $data['has_deposit'] = (bool) $latestApplication->has_deposit;
            $data['deposit_amount'] = $latestApplication->deposit_amount;
            $data['discord_username'] = $latestApplication->discord_username;
            $data['broker_uid'] = $latestApplication->broker_uid;
            $data['broker_email'] = $latestApplication->broker_email;
        }

        $documentPath = $request->file('document')
            ->store('trader_onboarding_documents');

        TraderOnboardingApplication::create([
            'user_id' => $user->id,
            'status' => TraderOnboardingApplication::STATUS_PENDING,
            'is_client' => (bool) $data['is_client'],
            'has_deposit' => (bool) $data['has_deposit'],
            'deposit_amount' => $data['has_deposit'] ? $data['deposit_amount'] : null,
            'discord_username' => $data['discord_username'],
            'broker_uid' => $data['broker_uid'],
            'broker_email' => $data['broker_email'],
            'document_path' => $documentPath,
            'trader_note' => $data['trader_note'] ?? null,
            'submitted_at' => now(),
        ]);

        return redirect()
            ->route('trader.onboarding.show')
            ->with([
                'message' => 'Your trader verification application has been submitted for administration review.',
                'alert-type' => 'success',
            ]);
    }

    public function adminIndex()
    {
        $this->ensureAdmin();

        $pendingApplications = TraderOnboardingApplication::with('trader')
            ->where('status', TraderOnboardingApplication::STATUS_PENDING)
            ->latest('submitted_at')
            ->get();

        $reviewedApplications = TraderOnboardingApplication::with(['trader', 'reviewer'])
            ->where('status', '!=', TraderOnboardingApplication::STATUS_PENDING)
            ->latest('reviewed_at')
            ->latest('id')
            ->take(80)
            ->get();

        $metrics = [
            'pending' => TraderOnboardingApplication::where('status', TraderOnboardingApplication::STATUS_PENDING)->count(),
            'approved' => TraderOnboardingApplication::where('status', TraderOnboardingApplication::STATUS_APPROVED)->count(),
            'resubmission' => TraderOnboardingApplication::where('status', TraderOnboardingApplication::STATUS_REJECTED_RESUBMITTABLE)->count(),
            'new_application' => TraderOnboardingApplication::where('status', TraderOnboardingApplication::STATUS_REJECTED_NEW_APPLICATION)->count(),
            'closed' => TraderOnboardingApplication::where('status', TraderOnboardingApplication::STATUS_REJECTED_FINAL)->count(),
        ];

        $rejectionReasons = TraderOnboardingApplication::rejectionReasons();

        return view('admin.trader_onboarding.index', compact(
            'pendingApplications',
            'reviewedApplications',
            'metrics',
            'rejectionReasons'
        ));
    }

    public function approve(TraderOnboardingApplication $application)
    {
        $this->ensureAdmin();

        if (! $application->isPending()) {
            return back()->with([
                'message' => 'Only pending applications can be approved.',
                'alert-type' => 'warning',
            ]);
        }

        $application->update([
            'status' => TraderOnboardingApplication::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
            'rejection_note' => null,
            'allow_resubmission' => true,
        ]);

        $application->trader?->forceFill(['status' => 1])->save();

        if ($application->trader) {
            AppNotificationService::notifyUser(
                (int) $application->trader->id,
                'Trader verification approved',
                'Your trader verification has been approved. Trading access is now unlocked.',
                route('trader.onboarding.show'),
                'verification'
            );
        }

        return back()->with([
            'message' => 'Trader application approved. Access is now unlocked.',
            'alert-type' => 'success',
        ]);
    }

    public function reject(Request $request, TraderOnboardingApplication $application)
    {
        $this->ensureAdmin();

        if (! $application->isPending()) {
            return back()->with([
                'message' => 'Only pending applications can be rejected.',
                'alert-type' => 'warning',
            ]);
        }

        $data = $request->validate([
            'rejection_reason' => ['required', Rule::in(array_keys(TraderOnboardingApplication::rejectionReasons()))],
            'rejection_note' => ['nullable', 'string', 'max:2000'],
            'rejection_decision' => ['required', Rule::in(['resubmit_documents', 'close_new_application', 'close_final'])],
        ]);

        $status = match ($data['rejection_decision']) {
            'resubmit_documents' => TraderOnboardingApplication::STATUS_REJECTED_RESUBMITTABLE,
            'close_new_application' => TraderOnboardingApplication::STATUS_REJECTED_NEW_APPLICATION,
            default => TraderOnboardingApplication::STATUS_REJECTED_FINAL,
        };
        $allowResubmission = $status !== TraderOnboardingApplication::STATUS_REJECTED_FINAL;

        $application->update([
            'status' => $status,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => $data['rejection_reason'],
            'rejection_note' => $data['rejection_note'] ?? null,
            'allow_resubmission' => $allowResubmission,
        ]);

        $message = match ($status) {
            TraderOnboardingApplication::STATUS_REJECTED_RESUBMITTABLE => 'Trader application rejected. The trader can resubmit supporting documents aligned with the submitted information.',
            TraderOnboardingApplication::STATUS_REJECTED_NEW_APPLICATION => 'Trader application closed. The trader can submit a new application with accurate information.',
            default => 'Trader application rejected and closed. The trader will be asked to contact HC person in charge.',
        };

        if ($application->trader) {
            AppNotificationService::notifyUser(
                (int) $application->trader->id,
                'Trader verification reviewed',
                $application->statusLabel() . '. Please open the verification page for details.',
                route('trader.onboarding.show'),
                'verification'
            );
        }

        return back()->with([
            'message' => $message,
            'alert-type' => $allowResubmission ? 'warning' : 'error',
        ]);
    }

    public function reopen(TraderOnboardingApplication $application)
    {
        $this->ensureAdmin();

        if (! $application->isHardClosed()) {
            return back()->with([
                'message' => 'Only hard-closed applications need to be reopened.',
                'alert-type' => 'info',
            ]);
        }

        $latestApplicationId = TraderOnboardingApplication::where('user_id', $application->user_id)
            ->latest('id')
            ->value('id');

        if ((int) $latestApplicationId !== (int) $application->id) {
            return back()->with([
                'message' => 'Only the latest hard-closed application can be reopened.',
                'alert-type' => 'warning',
            ]);
        }

        $application->update([
            'status' => TraderOnboardingApplication::STATUS_REJECTED_NEW_APPLICATION,
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => 'reopened_by_admin',
            'rejection_note' => 'Administration reopened this account so the trader can submit a new application.',
            'allow_resubmission' => true,
        ]);

        return back()->with([
            'message' => 'Application reopened. The trader can now submit a new application.',
            'alert-type' => 'success',
        ]);
    }

    public function downloadDocument(TraderOnboardingApplication $application)
    {
        $this->ensureAdmin();

        abort_unless($application->document_path && Storage::exists($application->document_path), 404);

        $extension = pathinfo($application->document_path, PATHINFO_EXTENSION) ?: 'file';
        $filename = 'trader-application-'.$application->id.'-'.$application->broker_uid.'.'.$extension;

        return Storage::download($application->document_path, $filename);
    }

    private function ensureTrader(): void
    {
        abort_unless(auth()->check() && (int) auth()->user()->role_id === 750, 403);
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }
}
