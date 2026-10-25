<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\TraderOnboardingApplication;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends MobileController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $applications = TraderOnboardingApplication::where('user_id', $user->id)->latest('id')->get();
        $latest = $applications->first();

        return $this->success([
            'user' => $user->only(['id', 'name', 'username', 'email', 'role_id', 'status', 'discord_connected_at']),
            'onboarding' => [
                'latest' => $latest,
                'applications' => $applications,
                'can_submit' => ! $latest || $latest->canResubmit() || $latest->canStartNewApplication(),
            ],
        ], 'Profile loaded.');
    }

    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
        ]);

        $emailChanged = $data['email'] !== $user->email;
        $user->fill($data);
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        return $this->success($user->fresh(), 'Profile updated successfully.');
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'string', 'min:8', 'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/'],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return $this->failure('The current password is incorrect.', ['current_password' => ['The current password is incorrect.']], 422);
        }

        $request->user()->forceFill(['password' => Hash::make($data['password'])])->save();
        $request->user()->tokens()->delete();

        return $this->success(null, 'Password updated. Please sign in again.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            return $this->failure('The password is incorrect.', ['password' => ['The password is incorrect.']], 422);
        }

        $user = $request->user();
        $user->tokens()->delete();
        $user->delete();

        return $this->success(null, 'Account deleted successfully.');
    }

    public function submitOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless((int) $user->role_id === 750, 403);
        $latest = TraderOnboardingApplication::where('user_id', $user->id)->latest('id')->first();
        if ($latest?->isApproved()) {
            return $this->success($latest, 'Your trader verification is already approved.');
        }
        if ($latest?->isPending()) {
            return $this->failure('Your application is already waiting for administration review.', [], 422);
        }
        if ($latest?->isHardClosed()) {
            return $this->failure('This application is closed. Please contact the HC person in charge.', [], 422);
        }

        $documentResubmission = $latest && $latest->canResubmit();
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
        if ($documentResubmission) {
            foreach (['is_client', 'has_deposit', 'deposit_amount', 'discord_username', 'broker_uid', 'broker_email'] as $field) {
                $data[$field] = $latest->{$field};
            }
        }
        $application = TraderOnboardingApplication::create([
            'user_id' => $user->id,
            'status' => TraderOnboardingApplication::STATUS_PENDING,
            'is_client' => (bool) $data['is_client'],
            'has_deposit' => (bool) $data['has_deposit'],
            'deposit_amount' => $data['has_deposit'] ? $data['deposit_amount'] : null,
            'discord_username' => $data['discord_username'],
            'broker_uid' => $data['broker_uid'],
            'broker_email' => $data['broker_email'],
            'document_path' => $request->file('document')->store('trader_onboarding_documents'),
            'trader_note' => $data['trader_note'] ?? null,
            'submitted_at' => now(),
        ]);

        return $this->success($application, 'Trader verification application submitted.', 201);
    }
}
