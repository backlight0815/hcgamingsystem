<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Support\PmsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MyProfileController extends Controller
{
    public function show(Request $request)
    {
        $user = $request->user()->load('pmsProfile', 'projectManagementIdentity');
        $projects = DB::table('pm_project_members')->join('pm_projects', 'pm_projects.id', '=', 'pm_project_members.project_id')
            ->where('pm_project_members.user_id', $user->id)->whereNull('pm_projects.deleted_at')
            ->orderBy('pm_projects.name')->get(['pm_projects.id', 'pm_projects.key', 'pm_projects.name', 'pm_projects.status', 'pm_project_members.role']);
        $work = PmWorkItem::query()->where(fn ($query) => $query->where('developer_id', $user->id)->orWhere('qa_id', $user->id))
            ->whereHas('project');
        if (! PmsAccess::administrator($user)) {
            $work->whereIn('project_id', $projects->pluck('id'));
        }
        $finished = ['Completed', 'Production Done', 'QAT Done', 'Cancelled'];
        $workSummary = [
            'developer' => (clone $work)->where('developer_id', $user->id)->count(),
            'qa' => (clone $work)->where('qa_id', $user->id)->count(),
            'open' => (clone $work)->whereNotIn('status', $finished)->count(),
            'completed' => (clone $work)->whereIn('status', ['Completed', 'Production Done', 'QAT Done'])->count(),
            'overdue' => (clone $work)->whereDate('due_date', '<', today())->whereNotIn('status', $finished)->count(),
            'active_projects' => $projects->where('status', 'Active')->count(),
        ];
        $assignedTickets = (clone $work)->with('project:id,key,name')->orderByDesc('updated_at')->limit(8)->get();
        return view('pms.my-profile', compact('user', 'projects', 'workSummary', 'assignedTickets'));
    }

    public function update(Request $request)
    {
        $user = $request->user()->load('pmsProfile', 'projectManagementIdentity');
        $identity = $user->projectManagementIdentity;
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('project_management_users', 'email')->ignore($identity?->id),
                Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:40', 'job_title' => 'nullable|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);
        $newPath = $request->file('photo')?->store('pms/profile', 'local');
        $oldPath = $user->pmsProfile?->photo_path;
        try {
            DB::transaction(function () use ($user, $identity, $data, $newPath): void {
                $user->update(['name' => $data['name']] + ($identity ? [] : ['email' => strtolower($data['email'])]));
                $identity?->update(['name' => $data['name'], 'email' => strtolower($data['email'])]);
                $profileChanges = [];
                foreach (['phone', 'job_title'] as $field) {
                    if (array_key_exists($field, $data)) $profileChanges[$field] = $data[$field];
                }
                if ($newPath) $profileChanges['photo_path'] = $newPath;
                if ($profileChanges) $user->pmsProfile?->update($profileChanges);
            });
        } catch (\Throwable $exception) {
            if ($newPath) Storage::disk('local')->delete($newPath);
            throw $exception;
        }
        if ($newPath && $oldPath) Storage::disk('local')->delete($oldPath);
        return back()->with('success', 'Profile updated.');
    }

    public function password(Request $request)
    {
        $data = $request->validate(['current_password' => 'required|string',
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::min(10)->mixedCase()->numbers()]]);
        $user = $request->user()->load('projectManagementIdentity');
        $identity = $user->projectManagementIdentity;
        $credential = $identity && ! $identity->legacy_shared_auth ? $identity : $user;
        if (! Hash::check($data['current_password'], $credential->password)) {
            throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
        }
        DB::transaction(function () use ($credential, $identity, $user, $data): void {
            $hash = Hash::make($data['password']);
            $credential->forceFill(['password' => $hash])->save();
            if ($identity?->legacy_shared_auth) $identity->forceFill(['password' => $hash])->save();
        });
        return back()->with('success', 'Password updated.');
    }

    public function photo(Request $request, User $user)
    {
        abort_unless(PmsAccess::workspace($request->user()), 403);
        $path = $user->pmsProfile?->photo_path;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }
}
