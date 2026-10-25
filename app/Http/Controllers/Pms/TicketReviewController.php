<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmWorkItem;
use App\Support\PmsAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TicketReviewController extends Controller
{
    public function submit(Request $request, PmWorkItem $ticket)
    {
        abort_unless($ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true), 404);
        abort_unless(PmsAccess::canCreateTicket($request->user(), $ticket->classification_key, $ticket->project), 403);
        DB::transaction(function () use ($request, $ticket): void {
            $locked = PmWorkItem::lockForUpdate()->findOrFail($ticket->id);
            $required = $locked->classification_key === 'REQ'
                ? ['description', 'business_justification', 'acceptance_criteria']
                : ['current_behavior', 'proposed_behavior', 'acceptance_criteria'];
            foreach ($required as $field) {
                if (blank($locked->{$field})) {
                    throw ValidationException::withMessages([$field => 'Complete this field before submitting for review.']);
                }
            }
            $latest = DB::table('pm_ticket_reviews')->where('issue_id', $locked->id)->orderByDesc('revision')->first();
            if ($latest && $latest->status !== 'changes_requested') {
                throw ValidationException::withMessages(['review' => 'This ticket is already pending review or approved.']);
            }
            $revision = ($latest->revision ?? 0) + 1;
            DB::table('pm_ticket_reviews')->insert([
                'issue_id' => $locked->id, 'revision' => $revision, 'status' => 'pending', 'status_code' => \App\Support\PmsStatusRegistry::code('ticket_review', 'pending'),
                'submitted_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->record($request, $locked, 'review.submitted', ['revision' => $revision]);
        });

        return $request->expectsJson() ? response()->json(['status' => 'pending'])
            : back()->with('success', 'Ticket submitted for review.');
    }

    public function decide(Request $request, PmWorkItem $ticket)
    {
        abort_unless($ticket->project && in_array($ticket->classification_key, ['REQ', 'ENH', 'EHN'], true), 404);
        abort_unless(PmsAccess::allows($request->user(), $ticket->project, 'manage'), 403);
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'changes_requested'])],
            'feedback' => 'required|string|min:3|max:10000',
        ]);
        DB::transaction(function () use ($request, $ticket, $data): void {
            $review = DB::table('pm_ticket_reviews')->where('issue_id', $ticket->id)
                ->orderByDesc('revision')->lockForUpdate()->first();
            if (! $review || $review->status !== 'pending') {
                throw ValidationException::withMessages(['review' => 'There is no pending review for this ticket.']);
            }
            abort_if((int) $review->submitted_by === $request->user()->id, 403, 'You cannot approve your own review request.');
            DB::table('pm_ticket_reviews')->where('id', $review->id)->update([
                'status' => $data['decision'], 'status_code' => \App\Support\PmsStatusRegistry::code('ticket_review', $data['decision']), 'feedback' => $data['feedback'],
                'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now(),
            ]);
            $this->record($request, $ticket, 'review.'.$data['decision'], [
                'revision' => $review->revision, 'feedback' => $data['feedback'],
            ]);
        });

        return $request->expectsJson() ? response()->json(['status' => $data['decision']])
            : back()->with('success', 'Review decision recorded.');
    }

    private function record(Request $request, PmWorkItem $ticket, string $event, array $details): void
    {
        DB::table('pm_issue_history')->insert([
            'issue_id' => $ticket->id, 'actor_id' => $request->user()->id,
            'event' => str_replace('review.', 'review_', $event),
            'reason' => $details['feedback'] ?? null,
            'changes' => json_encode($details), 'created_at' => now(),
        ]);
        DB::table('pm_audit_logs')->insert([
            'actor_id' => $request->user()->id, 'action' => $event,
            'subject_type' => 'issue', 'subject_id' => $ticket->id,
            'details' => json_encode($details), 'created_at' => now(),
        ]);
    }
}
