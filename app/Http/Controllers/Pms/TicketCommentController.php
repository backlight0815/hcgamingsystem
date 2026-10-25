<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmTicketComment;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\AppNotificationService;
use App\Services\PmsAttachmentService;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TicketCommentController extends Controller
{
    public function store(Request $request, PmWorkItem $ticket, PmsAttachmentService $attachments)
    {
        abort_unless(PmsTicketAccess::comment($request->user(), $ticket), 403);
        $data = $request->validate(['body' => 'required|string|max:30000', 'parent_id' => 'nullable|integer|exists:pm_ticket_comments,id', 'attachments' => 'nullable|array', 'attachments.*' => 'file']);
        if (! empty($data['parent_id'])) {
            $parent = PmTicketComment::findOrFail($data['parent_id']);
            if ((int) $parent->issue_id !== $ticket->id || $parent->parent_id) {
                throw \Illuminate\Validation\ValidationException::withMessages(['parent_id' => 'Reply to a top-level comment on this ticket.']);
            }
        }
        $files = $request->file('attachments', []);
        $attachments->validateFiles($files);
        $storedPaths = [];
        try {
            $comment = DB::transaction(function () use ($request, $ticket, $data, $files, $attachments, &$storedPaths): PmTicketComment {
                $comment = $ticket->comments()->create(['author_id' => $request->user()->id, 'parent_id' => $data['parent_id'] ?? null, 'body' => $data['body']]);
                $attachments->storeMany($ticket, $request->user(), $files, $comment, $storedPaths);
                $this->audit($request, $ticket, 'ticket.comment_added', ['comment_id' => $comment->id]);

                return $comment;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($storedPaths);
            throw $exception;
        }
        $this->notifyParticipants($request, $ticket, $comment);

        return $request->expectsJson() ? response()->json($comment->load(['author:id,name', 'attachments']), 201) : redirect(route('pms.tickets.show', $ticket).'#comment-'.$comment->id)->with('success', 'Comment added.');
    }

    public function update(Request $request, PmWorkItem $ticket, PmTicketComment $comment)
    {
        abort_unless((int) $comment->issue_id === $ticket->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless((int) $comment->author_id === $request->user()->id || PmsAccess::administrator($request->user()), 403);
        $data = $request->validate(['body' => 'required|string|max:30000']);
        DB::transaction(function () use ($request, $ticket, $comment, $data): void {
            DB::table('pm_comment_revisions')->insert(['comment_id' => $comment->id, 'actor_id' => $request->user()->id, 'body_before' => $comment->body, 'event' => 'edited', 'created_at' => now()]);
            $comment->update(['body' => $data['body'], 'edited_at' => now()]);
            $this->audit($request, $ticket, 'ticket.comment_edited', ['comment_id' => $comment->id]);
        });

        return $request->expectsJson() ? response()->json($comment->fresh()) : back()->with('success', 'Comment updated.');
    }

    public function destroy(Request $request, PmWorkItem $ticket, PmTicketComment $comment)
    {
        abort_unless((int) $comment->issue_id === $ticket->id && PmsTicketAccess::view($request->user(), $ticket), 404);
        abort_unless(PmsTicketAccess::manage($request->user(), $ticket), 403);
        DB::transaction(function () use ($request, $ticket, $comment): void {
            DB::table('pm_comment_revisions')->insert(['comment_id' => $comment->id, 'actor_id' => $request->user()->id, 'body_before' => $comment->body, 'event' => 'deleted', 'created_at' => now()]);
            $comment->delete();
            $this->audit($request, $ticket, 'ticket.comment_deleted', ['comment_id' => $comment->id]);
        });

        return $request->expectsJson() ? response()->json(['deleted' => true]) : back()->with('success', 'Comment removed from the conversation.');
    }

    private function notifyParticipants(Request $request, PmWorkItem $ticket, PmTicketComment $comment): void
    {
        preg_match_all('/@([A-Za-z0-9_]+)/', $comment->body, $matches);
        $mentionedIds = User::whereIn('username', array_unique($matches[1]))->pluck('id')->all();
        $recipients = array_unique(array_filter(array_merge([$ticket->reporter_id, $ticket->developer_id, $ticket->qa_id], $mentionedIds)));
        foreach ($recipients as $id) {
            if ((int) $id === $request->user()->id) {
                continue;
            }
            $user = User::find($id);
            if ($user && PmsTicketAccess::view($user, $ticket)) {
                AppNotificationService::notifyUser($user->id, "Comment on {$ticket->reference}", $request->user()->name.' added a comment.', route('pms.tickets.show', $ticket).'#comment-'.$comment->id, 'project_management', $request->user()->id);
            }
        }
    }

    private function audit(Request $request, PmWorkItem $ticket, string $action, array $details): void
    {
        DB::table('pm_audit_logs')->insert(['actor_id' => $request->user()->id, 'action' => $action, 'subject_type' => 'issue', 'subject_id' => $ticket->id, 'details' => json_encode($details), 'created_at' => now()]);
    }
}
