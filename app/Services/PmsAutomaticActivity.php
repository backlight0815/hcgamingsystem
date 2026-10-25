<?php

namespace App\Services;

use App\Models\User;
use App\Support\PmsAccess;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class PmsAutomaticActivity
{
    public function heartbeat(Request $request, User $user, array $data): array
    {
        $leaseId = $request->session()->get('pms_lease_id');
        $session = DB::table('pm_workspace_sessions')->where('id', $leaseId)
            ->where('user_id', $user->id)->whereNull('ended_at')->first();
        abort_unless($session, 401);

        $context = $this->context($request, $user, $data);
        $tabHash = hash('sha256', $data['tab_id']);
        return DB::transaction(function () use ($session, $user, $data, $context, $tabHash): array {
            // Serialize a user's overlapping tabs and devices before calculating eligible time.
            DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
            $now = now('UTC');
            $tab = DB::table('pm_workspace_tab_states')->where('session_id', $session->id)
                ->where('tab_hash', $tabHash)->lockForUpdate()->first();
            $focused = (bool) $data['visible'] && (bool) $data['focused'];
            $interaction = $focused && (bool) $data['interacted'];
            $idleSeconds = max(60, min(1800, (int) DB::table('pms_workspace_settings')->value('activity_idle_seconds') ?: (int) config('pms.active_idle_seconds', 300)));
            $maxGap = max(30, min(90, (int) config('pms.activity_heartbeat_seconds', 30) * 2));
            $added = 0;
            $priorInteraction = $tab?->last_interaction_at ? Carbon::parse($tab->last_interaction_at, 'UTC') : null;
            if ($tab && $tab->state === 'active') {
                $start = Carbon::parse($tab->last_seen_at, 'UTC');
                if ($start->diffInSeconds($now) <= $maxGap) {
                    $end = $priorInteraction?->copy()->addSeconds($idleSeconds);
                    if ($end && $end->greaterThan($now)) $end = $now;
                    if ($end && $end->greaterThan($start)) {
                        $added = $this->record($user->id, $session->id, $start, $end, 'active', [
                            'project_id' => $tab->project_id, 'ticket_id' => $tab->ticket_id,
                            'ticket_type' => $tab->ticket_type,
                        ]);
                    }
                    if ($focused && $end && $end->lessThan($now)) {
                        $this->record($user->id, $session->id, $end, $now, 'idle', $context);
                    }
                }
            } elseif ($tab && $tab->state === 'idle' && $focused) {
                $start = Carbon::parse($tab->last_seen_at, 'UTC');
                if ($start->diffInSeconds($now) <= $maxGap) {
                    $this->record($user->id, $session->id, $start, $now, 'idle', $context);
                }
            }
            $lastInteraction = $interaction ? $now : $priorInteraction;
            $state = ! $focused ? 'unfocused'
                : ($lastInteraction && $lastInteraction->copy()->addSeconds($idleSeconds)->greaterThan($now) ? 'active' : 'idle');
            if ($state === 'unfocused' && $tab && $tab->state !== 'unfocused') {
                DB::table('pm_workspace_activity_intervals')->insert([
                    'session_id' => $session->id, 'user_id' => $user->id,
                    'started_at' => $now, 'ended_at' => $now, 'duration_seconds' => 0,
                    'activity_date' => $now->copy()->timezone('Asia/Kuala_Lumpur')->toDateString(),
                    'project_id' => $tab->project_id, 'ticket_id' => $tab->ticket_id,
                    'ticket_type' => $tab->ticket_type,
                    'activity_classification' => 'Browser Unfocused', 'activity_status' => 'unfocused',
                ]);
            }
            $values = [
                'state' => $state, 'last_seen_at' => $now,
                'last_interaction_at' => $lastInteraction,
                'project_id' => $context['project_id'], 'ticket_id' => $context['ticket_id'],
                'ticket_type' => $context['ticket_type'], 'updated_at' => $now,
            ];
            if ($tab) {
                DB::table('pm_workspace_tab_states')->where('id', $tab->id)->update($values);
            } else {
                DB::table('pm_workspace_tab_states')->insert($values + [
                    'session_id' => $session->id, 'tab_hash' => $tabHash, 'created_at' => $now,
                ]);
            }
            if ($added) DB::table('pm_workspace_sessions')->where('id', $session->id)
                ->increment('active_seconds', $added, ['updated_at' => $now]);
            if ($interaction) DB::table('pm_workspace_sessions')->where('id', $session->id)
                ->update(['last_eligible_at' => $now, 'updated_at' => $now]);
            return ['status' => $state, 'active_seconds_added' => $added, 'idle_timeout_seconds' => $idleSeconds];
        });
    }

    public function record(int $userId, int $sessionId, Carbon $start, Carbon $end, string $status, array $context): int
    {
        if ($status === 'active') $this->subtractIdle($userId, $start, $end);
        $windows = [[$start->timestamp, $end->timestamp]];
        // Active intervals are subtracted across every session. Presence intervals are
        // informational; they never add to active totals or overlap a verified active span.
        $overlaps = DB::table('pm_workspace_activity_intervals')->where('user_id', $userId)
            ->where('started_at', '<', $end)->where('ended_at', '>', $start)
            ->whereIn('activity_status', $status === 'active' ? ['active'] : ['active', 'idle'])
            ->orderBy('started_at')->get(['started_at', 'ended_at']);
        foreach ($overlaps as $overlap) {
            $left = Carbon::parse($overlap->started_at, 'UTC')->timestamp;
            $right = Carbon::parse($overlap->ended_at, 'UTC')->timestamp;
            $next = [];
            foreach ($windows as [$a, $b]) {
                if ($right <= $a || $left >= $b) { $next[] = [$a, $b]; continue; }
                if ($left > $a) $next[] = [$a, $left];
                if ($right < $b) $next[] = [$right, $b];
            }
            $windows = $next;
        }
        $added = 0;
        foreach ($windows as [$a, $b]) {
            while ($a < $b) {
                $local = Carbon::createFromTimestampUTC($a)->timezone('Asia/Kuala_Lumpur');
                $midnight = $local->copy()->addDay()->startOfDay()->utc()->timestamp;
                $pieceEnd = min($b, $midnight);
                $seconds = $pieceEnd - $a;
                DB::table('pm_workspace_activity_intervals')->insert([
                    'session_id' => $sessionId, 'user_id' => $userId,
                    'started_at' => Carbon::createFromTimestampUTC($a),
                    'ended_at' => Carbon::createFromTimestampUTC($pieceEnd),
                    'duration_seconds' => $seconds, 'activity_date' => $local->toDateString(),
                    'project_id' => $context['project_id'] ?? null,
                    'ticket_id' => $context['ticket_id'] ?? null,
                    'ticket_type' => $context['ticket_type'] ?? null,
                    'activity_classification' => $context['ticket_id'] ?? null ? 'Ticket Activity'
                        : ($context['project_id'] ?? null ? 'Project Activity' : 'General PMS Activity'),
                    'activity_status' => $status,
                ]);
                $added += $seconds;
                $a = $pieceEnd;
            }
        }
        return $status === 'active' ? $added : 0;
    }

    private function subtractIdle(int $userId, Carbon $start, Carbon $end): void
    {
        $rows = DB::table('pm_workspace_activity_intervals')->where('user_id', $userId)
            ->where('activity_status', 'idle')->where('started_at', '<', $end)
            ->where('ended_at', '>', $start)->get();
        foreach ($rows as $row) {
            $left = Carbon::parse($row->started_at, 'UTC')->timestamp;
            $right = Carbon::parse($row->ended_at, 'UTC')->timestamp;
            DB::table('pm_workspace_activity_intervals')->where('id', $row->id)->delete();
            foreach ([[$left, min($right, $start->timestamp)], [max($left, $end->timestamp), $right]] as [$a, $b]) {
                if ($b <= $a) continue;
                DB::table('pm_workspace_activity_intervals')->insert([
                    'session_id' => $row->session_id, 'user_id' => $row->user_id,
                    'started_at' => Carbon::createFromTimestampUTC($a),
                    'ended_at' => Carbon::createFromTimestampUTC($b),
                    'duration_seconds' => $b - $a, 'activity_date' => $row->activity_date,
                    'project_id' => $row->project_id, 'ticket_id' => $row->ticket_id,
                    'ticket_type' => $row->ticket_type,
                    'activity_classification' => $row->activity_classification,
                    'activity_status' => 'idle',
                ]);
            }
        }
    }

    private function context(Request $request, User $user, array $data): array
    {
        $projectId = $data['project_id'] ?? null;
        $ticketId = $data['ticket_id'] ?? null;
        $message = $user->id.'|'.$request->session()->getId().'|'.($projectId ?: '').'|'.($ticketId ?: '');
        $valid = hash_equals(hash_hmac('sha256', $message, config('app.key')), (string) ($data['context_signature'] ?? ''));
        if (! $valid) return ['project_id' => null, 'ticket_id' => null, 'ticket_type' => null];
        $ticket = null;
        if ($ticketId) {
            $ticket = DB::table('pm_work_items')->where('id', $ticketId)->whereNull('deleted_at')->first();
            if (! $ticket || (int) $ticket->project_id !== (int) $projectId) return ['project_id' => null, 'ticket_id' => null, 'ticket_type' => null];
        }
        $project = $projectId ? \App\Models\PmProject::find($projectId) : null;
        if (! $project || ! PmsAccess::allows($user, $project, 'view')) return ['project_id' => null, 'ticket_id' => null, 'ticket_type' => null];
        return ['project_id' => $project->id, 'ticket_id' => $ticketId, 'ticket_type' => $ticket->classification_key ?? null];
    }
}
