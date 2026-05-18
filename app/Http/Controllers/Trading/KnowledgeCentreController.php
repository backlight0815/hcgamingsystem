<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeCentre;
use App\Models\KnowledgeCentreDiscord;
use App\Models\Community;
use App\Models\TradingPositionApplication;
use App\Models\User;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Services\AppNotificationService;
use App\Services\DiscordService; // ✅ Add this
class KnowledgeCentreController extends Controller
{
    /**
     * Display all knowledge centre entries
     */
    public function index()
    {
        $this->ensureKnowledgeManager();

        $query = KnowledgeCentre::with(['community', 'uploader'])->latest();

        if (! $this->currentUserIsAdmin()) {
            $query->where('uploaded_by', auth()->id());
        }

        $knowledge = $query->get();

            // -----------------------------
    // Statistics
    // -----------------------------
    $totalKnowledge = $knowledge->count(); // Total number of knowledge entries

    $pendingKnowledge = $knowledge->where('approval_status', 'pending')->count();
    $canApproveKnowledge = $this->currentUserIsAdmin();

    return view('admin.knowledge_centre.knowledge_all', compact(
        'knowledge',
        'totalKnowledge',
        'pendingKnowledge',
        'canApproveKnowledge'
    ));    }

    /**
     * Show create form
     */
    public function create()
    {
        $this->ensureKnowledgeManager();

        $communities = Community::where('status', 1)->get();
        return view('admin.knowledge_centre.knowledge_add', compact('communities'));
    }

    /**
     * Store new knowledge centre
     */
public function store(Request $request)
{
    $this->ensureKnowledgeManager();

    // 1️⃣ Validate input
    $data = $request->validate([
        'title'        => 'required|string|max:255',
        'description'  => 'nullable|string',
        'file'         => 'nullable|mimes:pdf,jpeg,jpg,png,gif|max:10240', // max 10MB
        'community_id' => 'nullable|exists:communities,id',
    ]);

    $filePath = null;
    $isImage = false;

    // 2️⃣ Handle file upload
    if ($request->hasFile('file') && $request->file('file')->isValid()) {
        $file = $request->file('file');
        $fileName = time() . '_' . preg_replace('/\s+/', '_', $file->getClientOriginalName());

        $destinationPath = public_path('upload/knowledge');
        if (!file_exists($destinationPath)) mkdir($destinationPath, 0755, true);

        $file->move($destinationPath, $fileName);
        $filePath = 'upload/knowledge/' . $fileName;

        // ✅ Check if it's an image safely
        $isImage = str_contains($file->getClientMimeType(), 'image');
    }

    // 3️⃣ Save KnowledgeCentre record
    $knowledge = KnowledgeCentre::create([
        'title'        => $data['title'],
        'description'  => $data['description'] ?? null,
        'file_path'    => $filePath,
        'community_id' => $data['community_id'] ?? null,
        'status' => $this->currentUserIsAdmin(),
        'uploaded_by' => auth()->id(),
        'approval_status' => $this->currentUserIsAdmin() ? 'approved' : 'pending',
        'approved_by' => $this->currentUserIsAdmin() ? auth()->id() : null,
        'approved_at' => $this->currentUserIsAdmin() ? now() : null,
    ]);

    // 4️⃣ Determine target communities
    $communities = $knowledge->community_id
        ? collect([$knowledge->community])->filter()
        : Community::where('status', 1)->get();

    // 5️⃣ Send to Discord
    if ($this->currentUserIsAdmin() && feature_enabled('DiscordIntegration') && $filePath) {
        foreach ($communities as $community) {
            $fullFilePath = public_path($filePath);

            if ($isImage) {
                if (!$community->discord_webhook_images) continue;

                $discordData = \App\Services\DiscordService::sendFile(
                    "**{$knowledge->title}**\n" . ($knowledge->description ?? ''),
                    $community->discord_webhook_images,
                    $fullFilePath
                );

                \App\Models\KnowledgeImage::create([
                    'knowledge_centre_id' => $knowledge->id,
                    'community_id'        => $community->id,
                    'image_path'          => $filePath,
                    'message_id'          => $discordData['message_id'] ?? null,
                    'channel_id'          => $discordData['channel_id'] ?? null,
                ]);
            } else {
                if (!$community->discord_webhook_knowledge) continue;

                $discordData = \App\Services\DiscordService::sendFile(
                    "**{$knowledge->title}**\n" . ($knowledge->description ?? ''),
                    $community->discord_webhook_knowledge,
                    $fullFilePath
                );

                \App\Models\KnowledgeCentreDiscord::create([
                    'knowledge_centre_id' => $knowledge->id,
                    'community_id'        => $community->id,
                    'message_id'          => $discordData['message_id'] ?? null,
                    'channel_id'          => $discordData['channel_id'] ?? null,
                ]);
            }
        }
    }

    if ($this->currentUserIsAdmin()) {
        AppNotificationService::notifyRoles(
            TradingPositionApplication::tradingMemberRoles(),
            'Knowledge Centre material uploaded',
            $knowledge->title . ' is now available in the Knowledge Centre.',
            route('trading.knowledge.centre.index'),
            'knowledge_centre'
        );
    } else {
        AppNotificationService::notifyAdmins(
            'Knowledge Centre item pending approval',
            $knowledge->title . ' was uploaded by a leader and needs administration approval.',
            route('knowledge.centre.index'),
            'knowledge_centre'
        );
    }

    return redirect()->route('knowledge.centre.index')->with([
        'success' => ! $this->currentUserIsAdmin()
            ? 'Knowledge Centre item submitted for administration approval.'
            : (feature_enabled('DiscordIntegration')
            ? 'Knowledge Centre item added + file sent to Discord!'
            : 'Knowledge Centre item added (Discord disabled).')
    ]);
}


    /**
     * Show edit form
     */
    public function edit($id)
    {
        $knowledge = KnowledgeCentre::findOrFail($id);
        $this->ensureCanManageKnowledge($knowledge);

        $communities = Community::where('status', 1)->get();
        return view('admin.knowledge_centre.knowledge_edit', compact('knowledge', 'communities'));
    }

    /**
     * Update knowledge centre
     */
public function update(Request $request, $id)
{
    $knowledge = KnowledgeCentre::findOrFail($id);
    $this->ensureCanManageKnowledge($knowledge);

    $request->validate([
        'title' => 'required|string|max:255',
        'pdf' => 'nullable|mimes:pdf',
        'community_id' => 'nullable|exists:communities,id',
        'description' => 'nullable|string',
    ]);

    // 1️⃣ Handle PDF upload
    if ($request->hasFile('pdf')) {
        $pdf = $request->file('pdf');
        $pdfName = time() . '_' . str_replace(' ', '_', $pdf->getClientOriginalName());
        $destinationPath = public_path('upload/knowledge');

        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        $pdf->move($destinationPath, $pdfName);

        // Delete old PDF
        if ($knowledge->file_path && file_exists(public_path($knowledge->file_path))) {
            unlink(public_path($knowledge->file_path));
        }

        $knowledge->file_path = 'upload/knowledge/' . $pdfName;
    }

    // 2️⃣ Update other fields
    $knowledge->title = $request->title;
    $knowledge->description = $request->description ?? null;
    $knowledge->community_id = $request->community_id;
    if (! $this->currentUserIsAdmin()) {
        $knowledge->status = false;
        $knowledge->approval_status = 'pending';
        $knowledge->approved_by = null;
        $knowledge->approved_at = null;
    }
    $knowledge->save();

    // 3️⃣ Update Discord message if integration is enabled
    if ($this->currentUserIsAdmin() && feature_enabled('DiscordIntegration')) {
        $communities = $knowledge->community_id
            ? collect([$knowledge->community])->filter()
            : Community::where('status', 1)->get();

        foreach ($communities as $community) {
            if (!$community->discord_webhook_knowledge) continue;

            $discordRecord = KnowledgeCentreDiscord::where('knowledge_centre_id', $knowledge->id)
                ->where('community_id', $community->id)
                ->first();

            try {
                $fileFullPath = $knowledge->file_path ? public_path($knowledge->file_path) : null;

                if ($discordRecord && $discordRecord->message_id) {
                    // ✅ Edit the existing Discord message with new PDF
                    Http::attach(
                        'file', file_get_contents($fileFullPath), basename($fileFullPath)
                    )->patch($community->discord_webhook_knowledge . "/messages/{$discordRecord->message_id}", [
                        'content' => "**{$knowledge->title}**\n" . ($knowledge->description ?? '')
                    ]);
                } else {
                    // Send as new message if none exists
                    $response = Http::attach(
                        'file', file_get_contents($fileFullPath), basename($fileFullPath)
                    )->post($community->discord_webhook_knowledge, [
                        'content' => "**{$knowledge->title}**\n" . ($knowledge->description ?? '')
                    ]);

                    if ($response->successful()) {
                        $discordData = $response->json();
                        KnowledgeCentreDiscord::updateOrCreate(
                            ['knowledge_centre_id' => $knowledge->id, 'community_id' => $community->id],
                            [
                                'community' => $community->name,
                                'message_id' => $discordData['id'] ?? null,
                                'channel_id' => $discordData['channel_id'] ?? null,
                            ]
                        );
                    }
                }
            } catch (\Exception $e) {
                \Log::error("Discord update failed for KnowledgeCentre {$knowledge->id}: " . $e->getMessage());
            }
        }
    }

    if ($this->currentUserIsAdmin() && $knowledge->status && $knowledge->approval_status === 'approved') {
        AppNotificationService::notifyRoles(
            TradingPositionApplication::tradingMemberRoles(),
            'Knowledge Centre material updated',
            $knowledge->title . ' has been updated in the Knowledge Centre.',
            route('trading.knowledge.centre.index'),
            'knowledge_centre'
        );
    } else {
        AppNotificationService::notifyAdmins(
            'Knowledge Centre update pending approval',
            $knowledge->title . ' was updated by a leader and needs administration approval.',
            route('knowledge.centre.index'),
            'knowledge_centre'
        );
    }

    return redirect()->route('knowledge.centre.index')
        ->with('success', 'Knowledge Centre item updated and Discord PDF replaced successfully.');
}


    /**
     * Delete knowledge centre
     */
    public function destroy($id)
    {
        $knowledge = KnowledgeCentre::findOrFail($id);
        $this->ensureCanManageKnowledge($knowledge);

        // Delete file if exists
        if ($knowledge->file_path && Storage::disk('public')->exists($knowledge->file_path)) {
            Storage::disk('public')->delete($knowledge->file_path);
        }

        $knowledge->delete();

        return redirect()->route('knowledge.centre.index')
            ->with('success', 'Knowledge Centre item deleted successfully.');
    }

    /**
     * Send knowledge centre to Discord
     */
public function sendToDiscord($id)
{
    $this->ensureAdmin();

    $knowledge = KnowledgeCentre::with('community')->findOrFail($id);

    if (!feature_enabled('DiscordIntegration')) {
        return back()->with('error', 'Discord integration is disabled.');
    }

    // Determine target communities
    $communities = $knowledge->community_id
        ? collect([$knowledge->community])->filter()
        : Community::where('status', 1)->get();

    if ($communities->isEmpty()) {
        return back()->with('error', 'No active communities found.');
    }

    $fileFullPath = $knowledge->file_path ? public_path($knowledge->file_path) : null;
    $sentCount = 0;

    foreach ($communities as $community) {
        if (empty($community->discord_webhook_knowledge)) {
            \Log::warning("Skipping community (no webhook): {$community->name}");
            continue;
        }

        try {
            // Always send a new Discord message
            if ($fileFullPath && file_exists($fileFullPath)) {
                $response = Http::attach(
                    'file',
                    file_get_contents($fileFullPath),
                    basename($fileFullPath)
                )->post($community->discord_webhook_knowledge . '?wait=true', [
                    'content' => "**{$knowledge->title}**\n" . ($knowledge->description ?? ''),
                ]);
            } else {
                $response = Http::post($community->discord_webhook_knowledge . '?wait=true', [
                    'content' => "**{$knowledge->title}**\n" . ($knowledge->description ?? ''),
                ]);
            }

            if ($response && $response->successful()) {
                $respData = $response->json();

                // Always create a new record in the DB
                KnowledgeCentreDiscord::create([
                    'knowledge_centre_id' => $knowledge->id,
                    'community_id'        => $community->id,
                    'community'           => $community->name,
                    'message_id'          => $respData['id'] ?? null,
                    'channel_id'          => $respData['channel_id'] ?? null,
                ]);

                $sentCount++;
            } else {
                \Log::error("Discord webhook failed for community {$community->name}: " . ($response->body() ?? 'No response'));
            }

        } catch (\Exception $e) {
            \Log::error("Discord send failed for KnowledgeCentre {$knowledge->id} ({$community->name}): " . $e->getMessage());
        }
    }

    if ($sentCount > 0) {
        return back()->with('success', "Knowledge Centre sent to Discord for {$sentCount} community(s).");
    }

    return back()->with('error', 'No Discord messages were sent.');
}
public function downloadZip()
{
    $this->ensureAdmin();

    $knowledgeFiles = KnowledgeCentre::whereNotNull('file_path')->get();

    if ($knowledgeFiles->isEmpty()) {
        return redirect()->back()->with('error', 'No PDF files available for download.');
    }

    $zipFileName = 'knowledge_files_' . now()->format('Ymd_His') . '.zip';
    $zipPath = storage_path('app/' . $zipFileName);

    $zip = new \ZipArchive();

    if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === true) {
        foreach ($knowledgeFiles as $file) {
            $fileFullPath = public_path($file->file_path);
            if (file_exists($fileFullPath)) {
                // Add file to zip with only file name, not full path
                $zip->addFile($fileFullPath, basename($fileFullPath));
            }
        }
        $zip->close();
    }

    return response()->download($zipPath)->deleteFileAfterSend(true);
}

public function approve(KnowledgeCentre $knowledge)
{
    $this->ensureAdmin();

    $knowledge->update([
        'status' => true,
        'approval_status' => 'approved',
        'approved_by' => auth()->id(),
        'approved_at' => now(),
        'approval_note' => null,
    ]);

    AppNotificationService::notifyRoles(
        TradingPositionApplication::tradingMemberRoles(),
        'Knowledge Centre material approved',
        $knowledge->title . ' is now available in the Knowledge Centre.',
        route('trading.knowledge.centre.index'),
        'knowledge_centre'
    );

    return back()->with('success', 'Knowledge Centre item approved and visible to the leader downline.');
}

public function traderIndex()
{
    $this->ensureTradingMember();

    $knowledge = $this->visibleKnowledgeFor(auth()->user())
        ->with(['community', 'uploader'])
        ->latest()
        ->get();

    return view('traders.knowledge_centre.index', compact('knowledge'));
}

private function ensureAdmin(): void
{
    abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
}

private function ensureTradingMember(): void
{
    abort_unless(auth()->check() && auth()->user()->isTradingMember(), 403);
}

private function ensureKnowledgeManager(): void
{
    abort_unless(auth()->check() && ($this->currentUserIsAdmin() || auth()->user()->isTradingLeader()), 403);
}

private function ensureCanManageKnowledge(KnowledgeCentre $knowledge): void
{
    abort_unless(auth()->check(), 403);

    if ($this->currentUserIsAdmin()) {
        return;
    }

    abort_unless(auth()->user()->isTradingLeader() && (int) $knowledge->uploaded_by === (int) auth()->id(), 403);
}

private function currentUserIsAdmin(): bool
{
    return auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true);
}

private function visibleKnowledgeFor(User $user)
{
    $leaderId = $this->leaderUplineId($user);

    return KnowledgeCentre::where('status', true)
        ->where('approval_status', 'approved')
        ->where(function ($query) use ($leaderId): void {
            $query->whereNull('uploaded_by')
                ->orWhereHas('uploader', fn ($uploader) => $uploader->whereIn('role_id', [1, 2]));

            if ($leaderId) {
                $query->orWhere('uploaded_by', $leaderId);
            }
        });
}

private function leaderUplineId(User $user): ?int
{
    if ((int) $user->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
        return (int) $user->id;
    }

    $current = $user;
    for ($depth = 0; $depth < 8; $depth++) {
        if (! $current->invited_by) {
            return null;
        }

        $upline = User::find($current->invited_by);
        if (! $upline) {
            return null;
        }

        if ((int) $upline->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
            return (int) $upline->id;
        }

        $current = $upline;
    }

    return null;
}



}
