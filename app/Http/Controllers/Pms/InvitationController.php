<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmProject;
use App\Services\PmsInvitationService;
use App\Support\PmsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function index(Request $request)
    {
        $projects = $this->manageableProjects($request);
        abort_unless(PmsAccess::administrator($request->user()) || $projects->isNotEmpty(), 403);
        $query = DB::table('pms_invitations')->leftJoin('users', 'users.id', '=', 'pms_invitations.created_by')
            ->leftJoin('pm_projects', 'pm_projects.id', '=', 'pms_invitations.project_id')
            ->select('pms_invitations.*', 'users.name as creator_name', 'pm_projects.name as project_name');
        if (! PmsAccess::administrator($request->user())) {
            $query->whereIn('pms_invitations.project_id', $projects->pluck('id'));
        }
        if ($request->filled('q')) {
            $term = trim((string) $request->q);
            $query->where(fn ($q) => $q->where('pms_invitations.code', 'like', '%'.$term.'%')
                ->orWhere('pms_invitations.email', 'like', '%'.$term.'%'));
        }
        match ($request->input('status')) {
            'revoked' => $query->whereNotNull('revoked_at'),
            'expired' => $query->whereNull('revoked_at')->where('expires_at', '<=', now()),
            'accepted' => $query->whereNull('revoked_at')->where('used_count', '>', 0),
            'pending' => $query->whereNull('revoked_at')->where('expires_at', '>', now())->whereColumn('used_count', '<', 'max_uses'),
            default => null,
        };
        $invitations = $query->orderByDesc('pms_invitations.id')->paginate(25)->withQueryString();
        $invitations->getCollection()->transform(function ($invite) {
            $invite->link = ! $invite->revoked_at && $invite->expires_at > now()->toDateTimeString() && $invite->used_count < $invite->max_uses
                ? route('pms.invite', ['token' => Crypt::decryptString($invite->token_ciphertext)]) : null;
            unset($invite->token_ciphertext, $invite->token_hash);
            return $invite;
        });
        $roles = PmsAccess::administrator($request->user())
            ? array_values(array_diff(PmsAccess::ROLES, ['Project Administrator'])) : PmsAccess::MANAGER_ASSIGNABLE_ROLES;
        $departments = DB::table('pms_departments')->where('active', true)->orderBy('name')->get();
        $positions = DB::table('pms_job_positions')->join('pms_department_positions', 'pms_department_positions.position_id', '=', 'pms_job_positions.id')
            ->where('pms_job_positions.active', true)->get(['pms_job_positions.id', 'pms_job_positions.name', 'pms_department_positions.department_id']);

        return $request->expectsJson() ? response()->json($invitations)
            : view('pms.invitations', compact('invitations', 'projects', 'roles', 'departments', 'positions'));
    }

    public function store(Request $request)
    {
        $projects = $this->manageableProjects($request);
        $administrator = PmsAccess::administrator($request->user());
        abort_unless($administrator || $projects->isNotEmpty(), 403);
        $roles = $administrator ? array_values(array_diff(PmsAccess::ROLES, ['Project Administrator'])) : PmsAccess::MANAGER_ASSIGNABLE_ROLES;
        $data = $request->validate([
            'email' => 'nullable|email|max:255', 'email_restricted' => 'nullable|boolean',
            'role' => ['required', Rule::in($roles)],
            'project_id' => ['required', 'integer', 'exists:pm_projects,id'],
            'expires_at' => 'required|date|after:now', 'max_uses' => 'required|integer|in:1',
            'description' => 'nullable|string|max:2000',
            'referral_code' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_-]+$/|unique:pms_invitations,referral_code',
            'invited_name' => 'nullable|string|max:255',
            'department_id' => 'nullable|integer|required_with:job_position_id|exists:pms_departments,id',
            'job_position_id' => 'nullable|integer|required_with:department_id|exists:pms_job_positions,id',
        ]);
        if (! empty($data['department_id'])) {
            OrganizationSettingsController::validatePair((int) $data['department_id'], (int) $data['job_position_id']);
        }
        if (! empty($data['project_id'])) {
            abort_unless($projects->contains('id', (int) $data['project_id']), 403);
        }
        if (! $administrator) {
            abort_unless(! empty($data['project_id']), 403);
        }
        if (! empty($data['email_restricted']) && empty($data['email'])) {
            throw \Illuminate\Validation\ValidationException::withMessages(['email' => 'Enter an email to restrict this invitation.']);
        }
        $created = DB::transaction(fn () => $this->createInvitation($request, $data));

        return $request->expectsJson() ? response()->json($created, 201)
            : redirect()->route('pms.invitations.index')->with('success', 'Invitation created.')->with('invitation_link', $created['link']);
    }

    public function batch(Request $request)
    {
        $projects = $this->manageableProjects($request);
        $administrator = PmsAccess::administrator($request->user());
        abort_unless($administrator || $projects->isNotEmpty(), 403);
        $roles = $administrator ? array_values(array_diff(PmsAccess::ROLES, ['Project Administrator'])) : PmsAccess::MANAGER_ASSIGNABLE_ROLES;
        if ($request->filled('emails')) {
            $emails = preg_split('/\r\n|\r|\n|,/', (string) $request->input('emails'));
            $request->merge(['members' => array_map(fn ($email) => ['email' => strtolower(trim($email))],
                array_values(array_filter($emails, fn ($email) => trim($email) !== '')))]);
        }
        $data = $request->validate([
            'members' => 'required|array|min:1|max:50',
            'members.*.name' => 'nullable|string|max:255',
            'members.*.email' => 'required|email|max:255|distinct',
            'role' => ['required', Rule::in($roles)],
            'project_id' => ['required', 'integer', 'exists:pm_projects,id'],
            'expires_at' => 'required|date|after:now', 'description' => 'nullable|string|max:2000',
            'department_id' => 'nullable|integer|required_with:job_position_id|exists:pms_departments,id',
            'job_position_id' => 'nullable|integer|required_with:department_id|exists:pms_job_positions,id',
        ]);
        $normalized = collect($data['members'])->pluck('email')->map(fn ($email) => strtolower($email));
        if ($normalized->unique()->count() !== $normalized->count()) {
            throw \Illuminate\Validation\ValidationException::withMessages(['members' => 'Each recipient email must appear once.']);
        }
        if (! empty($data['department_id'])) {
            OrganizationSettingsController::validatePair((int) $data['department_id'], (int) $data['job_position_id']);
        }
        if (! empty($data['project_id'])) abort_unless($projects->contains('id', (int) $data['project_id']), 403);
        if (! $administrator) abort_unless(! empty($data['project_id']), 403);
        $created = DB::transaction(function () use ($request, $data): array {
            $links = [];
            foreach ($data['members'] as $member) {
                $links[] = $this->createInvitation($request, $data + ['max_uses' => 1], $member);
            }
            return $links;
        });

        return $request->expectsJson() ? response()->json(['invitations' => $created], 201)
            : redirect()->route('pms.invitations.index')->with('success', count($created).' invitations created.')
                ->with('invitation_links', $created);
    }

    private function createInvitation(Request $request, array $data, ?array $member = null): array
    {
        $token = bin2hex(random_bytes(32));
        $id = DB::table('pms_invitations')->insertGetId([
            'code' => 'TMP-'.Str::upper(Str::random(16)),
            'token_hash' => hash('sha256', $token), 'token_ciphertext' => Crypt::encryptString($token),
            'email' => isset($member['email']) ? strtolower($member['email']) : (empty($data['email']) ? null : strtolower($data['email'])),
            'email_restricted' => $member !== null || (bool) ($data['email_restricted'] ?? false),
            'role' => $data['role'], 'project_id' => $data['project_id'] ?? null,
            'description' => $data['description'] ?? null, 'created_by' => $request->user()->id,
            'invited_name' => $member['name'] ?? $data['invited_name'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'job_position_id' => $data['job_position_id'] ?? null,
            'expires_at' => $data['expires_at'], 'max_uses' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $code = 'PM-INV-'.now()->format('Y').'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        DB::table('pms_invitations')->where('id', $id)->update([
            'code' => $code, 'referral_code' => $data['referral_code'] ?? $code,
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => 'invitation.created',
            'subject_type' => 'invitation', 'subject_id' => $id,
            'details' => json_encode(['role' => $data['role'], 'project_id' => $data['project_id'] ?? null,
                'department_id' => $data['department_id'] ?? null, 'job_position_id' => $data['job_position_id'] ?? null]),
            'created_at' => now(),
        ]);
        return ['code' => $code, 'email' => isset($member['email']) ? strtolower($member['email']) : (empty($data['email']) ? null : strtolower($data['email'])),
            'link' => route('pms.invite', ['token' => $token])];
    }

    public function revoke(Request $request, int $invitation)
    {
        $invite = DB::table('pms_invitations')->where('id', $invitation)->first();
        abort_unless($invite, 404);
        abort_unless(PmsAccess::administrator($request->user())
            || $invite->project_id && $this->manageableProjects($request)->contains('id', (int) $invite->project_id), 403);
        DB::transaction(function () use ($request, $invite): void {
            DB::table('pms_invitations')->where('id', $invite->id)->whereNull('revoked_at')->update([
                'revoked_at' => now(), 'revoked_by' => $request->user()->id, 'updated_at' => now(),
            ]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'invitation.revoked',
                'subject_type' => 'invitation', 'subject_id' => $invite->id, 'created_at' => now(),
            ]);
        });

        return $request->expectsJson() ? response()->json(['revoked' => true]) : back()->with('success', 'Invitation revoked.');
    }

    public function accept(Request $request, PmsInvitationService $invitations)
    {
        $data = $request->validate(['invite' => 'required|string']);
        $invitations->accept($data['invite'], fn () => $request->user());
        return $request->expectsJson() ? response()->json(['accepted' => true])
            : redirect()->route('pms.dashboard')->with('success', 'Invitation accepted. Welcome to the project.');
    }

    public function reissue(Request $request, int $invitation)
    {
        $invite = DB::table('pms_invitations')->where('id', $invitation)->first();
        abort_unless($invite, 404);
        abort_if(! $invite->email || $invite->revoked_at || $invite->used_count >= $invite->max_uses, 422,
            'Create a new personal invitation for this recipient.');
        abort_unless(PmsAccess::administrator($request->user())
            || $invite->project_id && $this->manageableProjects($request)->contains('id', (int) $invite->project_id), 403);
        $token = bin2hex(random_bytes(32));
        $new = DB::transaction(function () use ($request, $invite, $token) {
            $invite = DB::table('pms_invitations')->where('id', $invite->id)->lockForUpdate()->first();
            abort_if(! $invite || $invite->revoked_at || $invite->used_count >= $invite->max_uses, 422,
                'Create a new personal invitation for this recipient.');
            DB::table('pms_invitations')->where('id', $invite->id)->update([
                'revoked_at' => now(), 'revoked_by' => $request->user()->id, 'updated_at' => now(),
            ]);
            $id = DB::table('pms_invitations')->insertGetId([
                'code' => 'TMP-'.Str::upper(Str::random(16)),
                'token_hash' => hash('sha256', $token), 'token_ciphertext' => Crypt::encryptString($token),
                'email' => $invite->email, 'email_restricted' => $invite->email_restricted,
                'role' => $invite->role, 'project_id' => $invite->project_id,
                'invited_name' => $invite->invited_name,
                'department_id' => $invite->department_id, 'job_position_id' => $invite->job_position_id,
                'description' => $invite->description, 'created_by' => $request->user()->id,
                'expires_at' => now()->addDays(7), 'max_uses' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $code = 'PM-INV-'.now()->format('Y').'-'.str_pad((string) $id, 6, '0', STR_PAD_LEFT);
            DB::table('pms_invitations')->where('id', $id)->update(['code' => $code, 'referral_code' => $code]);
            DB::table('pm_audit_logs')->insert([
                'actor_id' => $request->user()->id, 'action' => 'invitation.reissued',
                'subject_type' => 'invitation', 'subject_id' => $id,
                'details' => json_encode(['previous_invitation_id' => $invite->id]), 'created_at' => now(),
            ]);
            return $code;
        });
        $link = route('pms.invite', ['token' => $token]);
        return $request->expectsJson() ? response()->json(['code' => $new, 'link' => $link], 201)
            : redirect()->route('pms.invitations.index')->with('success', 'Invitation reissued.')->with('invitation_link', $link);
    }

    private function manageableProjects(Request $request)
    {
        $query = PmProject::whereNull('archived_at');
        if (! PmsAccess::administrator($request->user())) {
            $query->whereHas('members', fn ($q) => $q->where('users.id', $request->user()->id)
                ->whereIn('pm_project_members.role', ['Project Manager', 'Project Administrator']));
        }
        return $query->orderBy('name')->get(['id', 'key', 'name']);
    }
}
