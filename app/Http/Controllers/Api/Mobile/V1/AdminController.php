<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\FinalExamAttempt;
use App\Models\Role;
use App\Models\TraderOnboardingApplication;
use App\Models\TradingJournal;
use App\Models\TradingPositionApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class AdminController extends MobileController
{
    public function overview(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return $this->success([
            'counts' => [
                'users' => User::count(),
                'trading_journals' => TradingJournal::count(),
                'pending_onboarding' => Schema::hasTable('trader_onboarding_applications')
                    ? TraderOnboardingApplication::where('status', 'pending')->count()
                    : 0,
                'pending_positions' => Schema::hasTable('trading_position_applications')
                    ? TradingPositionApplication::where('status', 'pending')->count()
                    : 0,
                'failed_final_exams' => Schema::hasTable('final_exam_attempts')
                    ? FinalExamAttempt::where('status', FinalExamAttempt::STATUS_FAILED)->count()
                    : 0,
            ],
            'recent_users' => User::query()
                ->with('role:id,name')
                ->latest('id')
                ->limit(25)
                ->get(['id', 'name', 'username', 'email', 'role_id', 'status', 'created_at']),
        ], 'Admin trading overview loaded.');
    }

    public function users(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'role_id' => ['nullable', 'integer', 'exists:roles,id'],
            'status' => ['nullable', Rule::in([0, 1, '0', '1'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $users = User::query()
            ->with('role:id,name')
            ->when($data['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($builder) use ($search): void {
                    $builder->where('name', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when(isset($data['role_id']), fn ($query) => $query->where('role_id', $data['role_id']))
            ->when(isset($data['status']), fn ($query) => $query->where('status', $data['status']))
            ->latest('id')
            ->paginate(30);

        return $this->success([
            'users' => $users->items(),
            'pagination' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'total' => $users->total(),
            ],
            'roles' => Role::orderBy('id')->get(['id', 'name', 'description']),
        ], 'Users loaded.');
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_if(! $request->user()->isGlobalSuperAdmin() && (int) $user->role_id === User::ROLE_SUPER_ADMIN, 403);
        abort_if((int) $request->user()->id === (int) $user->id, 422, 'Use your profile settings to update your own account.');
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'role_id' => ['sometimes', 'required', 'integer', 'exists:roles,id'],
            'status' => ['sometimes', 'required', Rule::in([0, 1, '0', '1'])],
        ]);
        abort_if(! $request->user()->isGlobalSuperAdmin() && (int) ($data['role_id'] ?? 0) === User::ROLE_SUPER_ADMIN, 403);
        if ((int) $user->role_id === 1 && isset($data['role_id']) && (int) $data['role_id'] !== 1) {
            abort_if(User::where('role_id', 1)->count() <= 1, 422, 'The final super administrator cannot be demoted.');
        }
        $user->update($data);

        return $this->success($user->fresh('role:id,name'), 'User account updated.');
    }

    public function onboarding(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless(Schema::hasTable('trader_onboarding_applications'), 404);

        return $this->success([
            'pending' => TraderOnboardingApplication::with('trader:id,name,username,email,status')
                ->where('status', TraderOnboardingApplication::STATUS_PENDING)
                ->latest('submitted_at')
                ->get(),
            'recent' => TraderOnboardingApplication::with(['trader:id,name,username,email,status', 'reviewer:id,name'])
                ->where('status', '!=', TraderOnboardingApplication::STATUS_PENDING)
                ->latest('reviewed_at')
                ->limit(40)
                ->get(),
            'rejection_reasons' => TraderOnboardingApplication::rejectionReasons(),
        ], 'Onboarding reviews loaded.');
    }

    public function reviewOnboarding(Request $request, TraderOnboardingApplication $application): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless($application->isPending(), 422, 'Only pending applications can be reviewed.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'resubmit_documents', 'close_new_application', 'close_final'])],
            'rejection_reason' => ['nullable', Rule::requiredIf($request->input('decision') !== 'approve'), Rule::in(array_keys(TraderOnboardingApplication::rejectionReasons()))],
            'rejection_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $status = match ($data['decision']) {
            'approve' => TraderOnboardingApplication::STATUS_APPROVED,
            'resubmit_documents' => TraderOnboardingApplication::STATUS_REJECTED_RESUBMITTABLE,
            'close_new_application' => TraderOnboardingApplication::STATUS_REJECTED_NEW_APPLICATION,
            default => TraderOnboardingApplication::STATUS_REJECTED_FINAL,
        };
        $application->update([
            'status' => $status,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $status === TraderOnboardingApplication::STATUS_APPROVED ? null : $data['rejection_reason'],
            'rejection_note' => $status === TraderOnboardingApplication::STATUS_APPROVED ? null : ($data['rejection_note'] ?? null),
            'allow_resubmission' => $status !== TraderOnboardingApplication::STATUS_REJECTED_FINAL,
        ]);
        if ($status === TraderOnboardingApplication::STATUS_APPROVED) {
            $application->trader?->forceFill(['status' => 1])->save();
        }

        return $this->success($application->fresh(['trader:id,name,username,email,status', 'reviewer:id,name']), 'Onboarding review saved.');
    }

    public function positions(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return $this->success(TradingPositionApplication::with('applicant:id,name,username,email,role_id')
            ->latest('submitted_at')
            ->limit(80)
            ->get(), 'Position applications loaded.');
    }

    public function reviewPosition(Request $request, TradingPositionApplication $application): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless($application->isPending(), 422, 'Only pending applications can be reviewed.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approve', 'reject'])],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $approved = $data['decision'] === 'approve';
        $application->update([
            'status' => $approved ? TradingPositionApplication::STATUS_APPROVED : TradingPositionApplication::STATUS_REJECTED,
            'reviewed_by' => $request->user()->id,
            'review_note' => $data['review_note'] ?? null,
            'reviewed_at' => now(),
        ]);
        if ($approved) {
            $application->applicant?->update(['role_id' => $application->requested_role_id]);
        }

        return $this->success($application->fresh('applicant:id,name,username,email,role_id'), 'Position review saved.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()->isBackofficeAdministrator(), 403);
    }
}
