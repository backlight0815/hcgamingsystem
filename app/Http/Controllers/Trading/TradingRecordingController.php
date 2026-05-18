<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\TradingRecording;
use App\Models\TradingRecordingMaterial;
use App\Models\TradingPositionApplication;
use App\Models\User;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradingRecordingController extends Controller
{
    public function adminIndex()
    {
        $this->ensureRecordingManager();

        $query = TradingRecording::with('uploader')->withCount('materials')->latest();

        if (! $this->currentUserIsAdmin()) {
            $query->where('uploaded_by', auth()->id());
        }

        $recordings = $query->paginate(20);
        $totalRecordings = (clone $query)->count();
        $activeRecordings = (clone $query)->where('status', true)->where('approval_status', 'approved')->count();
        $pendingRecordings = (clone $query)->where('approval_status', 'pending')->count();
        $canApproveRecordings = $this->currentUserIsAdmin();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('admin.trading.recordings.index')],
        ];

        return view('admin.trading_recordings.index', compact(
            'recordings',
            'totalRecordings',
            'activeRecordings',
            'pendingRecordings',
            'canApproveRecordings',
            'breadcrumbData'
        ));
    }

    public function create()
    {
        $this->ensureRecordingManager();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('admin.trading.recordings.index')],
            ['label' => 'Add Recording', 'url' => route('admin.trading.recordings.create')],
        ];

        return view('admin.trading_recordings.create', compact('breadcrumbData'));
    }

    public function store(Request $request)
    {
        $this->ensureRecordingManager();

        $data = $this->validatedRecordingData($request);
        $data['uploaded_by'] = auth()->id();
        $data['approval_status'] = $this->currentUserIsAdmin() ? 'approved' : 'pending';
        $data['approved_by'] = $this->currentUserIsAdmin() ? auth()->id() : null;
        $data['approved_at'] = $this->currentUserIsAdmin() ? now() : null;

        if (! $this->currentUserIsAdmin()) {
            $data['status'] = false;
        }

        $recording = TradingRecording::create($data);
        $materialCount = $this->storeMaterials($request, $recording);

        if ($this->currentUserIsAdmin() && $recording->status && $recording->approval_status === 'approved') {
            AppNotificationService::notifyRoles(
                TradingPositionApplication::tradingMemberRoles(),
                'New recording class uploaded',
                $recording->title . ($materialCount > 0 ? ' includes new class materials.' : ' is now available.'),
                route('trading.recordings.index'),
                'class_material'
            );
        }

        return redirect()
            ->route('admin.trading.recordings.index')
            ->with('success', $this->currentUserIsAdmin()
                ? 'Recording class added successfully.'
                : 'Recording class submitted for administration approval.');
    }

    public function show(TradingRecording $recording)
    {
        $this->ensureCanManageRecording($recording);
        $recording->load(['materials.uploader', 'uploader']);

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('admin.trading.recordings.index')],
            ['label' => $recording->title, 'url' => route('admin.trading.recordings.show', $recording->id)],
        ];

        return view('admin.trading_recordings.show', compact('recording', 'breadcrumbData'));
    }

    public function edit(TradingRecording $recording)
    {
        $this->ensureCanManageRecording($recording);
        $recording->load('materials');

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('admin.trading.recordings.index')],
            ['label' => 'Edit Recording', 'url' => route('admin.trading.recordings.edit', $recording->id)],
        ];

        return view('admin.trading_recordings.edit', compact('recording', 'breadcrumbData'));
    }

    public function update(Request $request, TradingRecording $recording)
    {
        $this->ensureCanManageRecording($recording);

        $data = $this->validatedRecordingData($request, $recording->status);
        if (! $this->currentUserIsAdmin()) {
            $data['status'] = false;
            $data['approval_status'] = 'pending';
            $data['approved_by'] = null;
            $data['approved_at'] = null;
        }

        $recording->update($data);
        $materialCount = $this->storeMaterials($request, $recording);
        $freshRecording = $recording->fresh();

        if ($materialCount > 0 && $freshRecording->status && $freshRecording->approval_status === 'approved') {
            AppNotificationService::notifyRoles(
                TradingPositionApplication::tradingMemberRoles(),
                'Class materials uploaded',
                'New materials were added to ' . $recording->title . '.',
                route('trading.recordings.index'),
                'class_material'
            );
        }

        return redirect()
            ->route('admin.trading.recordings.index')
            ->with('success', $this->currentUserIsAdmin()
                ? 'Recording class updated successfully.'
                : 'Recording class updated and sent back for administration approval.');
    }

    public function destroy(TradingRecording $recording)
    {
        $this->ensureCanManageRecording($recording);

        $recording->load('materials');
        foreach ($recording->materials as $material) {
            $this->deleteMaterialFile($material);
        }

        $recording->delete();

        return redirect()
            ->route('admin.trading.recordings.index')
            ->with('success', 'Recording class deleted successfully.');
    }

    public function traderIndex()
    {
        $this->ensureTradingMember();

        $recordings = $this->visibleRecordingsFor(auth()->user())
            ->withCount('materials')
            ->latest()
            ->paginate(12);

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('trading.recordings.index')],
        ];

        return view('traders.trading_recordings.index', compact('recordings', 'breadcrumbData'));
    }

    public function traderView(Request $request, TradingRecording $recording)
    {
        $this->ensureTradingMember();
        $this->ensureActiveRecording($recording);
        $this->ensureRecordingVisibleToUser($recording, auth()->user());
        $this->validateCurrentPassword($request);
        $recording->load('materials');

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Recording Classes', 'url' => route('trading.recordings.index')],
            ['label' => $recording->title, 'url' => route('trading.recordings.index')],
        ];

        return view('traders.trading_recordings.show', compact('recording', 'breadcrumbData'));
    }

    public function traderDownload(Request $request, TradingRecording $recording)
    {
        $this->ensureTradingMember();
        $this->ensureActiveRecording($recording);
        $this->ensureRecordingVisibleToUser($recording, auth()->user());

        try {
            $this->validateCurrentPassword($request);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('trading.recordings.index')
                ->withErrors($exception->errors());
        }

        return redirect()->away($recording->effective_download_url);
    }

    public function adminDownloadMaterial(TradingRecording $recording, TradingRecordingMaterial $material)
    {
        $this->ensureCanManageRecording($recording);
        $this->ensureMaterialBelongsToRecording($recording, $material);
        $this->ensureStoredMaterialExists($material);

        $material->increment('download_count');

        return Storage::disk('local')->download(
            $material->file_path,
            $material->original_filename
        );
    }

    public function destroyMaterial(TradingRecording $recording, TradingRecordingMaterial $material)
    {
        $this->ensureCanManageRecording($recording);
        $this->ensureMaterialBelongsToRecording($recording, $material);

        $this->deleteMaterialFile($material);
        $material->delete();

        return back()->with('success', 'Class material removed successfully.');
    }

    public function traderDownloadMaterial(Request $request, TradingRecording $recording, TradingRecordingMaterial $material)
    {
        $this->ensureTradingMember();
        $this->ensureActiveRecording($recording);
        $this->ensureRecordingVisibleToUser($recording, auth()->user());
        $this->ensureMaterialBelongsToRecording($recording, $material);
        $this->validateCurrentPassword($request);
        $this->ensureStoredMaterialExists($material);

        $material->increment('download_count');

        return Storage::disk('local')->download(
            $material->file_path,
            $material->original_filename
        );
    }

    public function approve(TradingRecording $recording)
    {
        $this->ensureAdmin();

        $recording->update([
            'approval_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'approval_note' => null,
            'status' => true,
        ]);

        AppNotificationService::notifyRoles(
            TradingPositionApplication::tradingMemberRoles(),
            'Recording class approved',
            $recording->title . ' is now available in the recording class library.',
            route('trading.recordings.index'),
            'class_material'
        );

        return back()->with('success', 'Recording class approved and visible to the leader downline.');
    }

    private function validatedRecordingData(Request $request, bool $defaultActive = true): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'video_url' => ['required', 'url', 'max:2048'],
            'download_url' => ['nullable', 'url', 'max:2048'],
            'source_name' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'boolean'],
            'materials' => ['nullable', 'array', 'max:10'],
            'materials.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpeg,jpg,png,webp,zip', 'max:51200'],
        ]);

        $status = $request->input('status', $defaultActive ? '1' : '0');
        $data['status'] = filter_var($status, FILTER_VALIDATE_BOOLEAN);
        unset($data['materials']);

        return $data;
    }

    private function storeMaterials(Request $request, TradingRecording $recording): int
    {
        if (! $request->hasFile('materials')) {
            return 0;
        }

        $storedCount = 0;

        foreach ($request->file('materials') as $file) {
            if (! $file || ! $file->isValid()) {
                continue;
            }

            $extension = strtolower($file->getClientOriginalExtension());
            $fileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
            $filePath = $file->storeAs('trading_recording_materials', $fileName, 'local');
            $originalName = $file->getClientOriginalName();

            $recording->materials()->create([
                'uploaded_by' => auth()->id(),
                'title' => pathinfo($originalName, PATHINFO_FILENAME) ?: $originalName,
                'file_path' => $filePath,
                'original_filename' => $originalName,
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?: 0,
            ]);

            $storedCount++;
        }

        return $storedCount;
    }

    private function ensureMaterialBelongsToRecording(TradingRecording $recording, TradingRecordingMaterial $material): void
    {
        abort_unless((int) $material->trading_recording_id === (int) $recording->id, 404);
    }

    private function ensureStoredMaterialExists(TradingRecordingMaterial $material): void
    {
        abort_unless($material->file_path && Storage::disk('local')->exists($material->file_path), 404);
    }

    private function deleteMaterialFile(TradingRecordingMaterial $material): void
    {
        if ($material->file_path && Storage::disk('local')->exists($material->file_path)) {
            Storage::disk('local')->delete($material->file_path);
        }
    }

    private function validateCurrentPassword(Request $request): void
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($request->password, $request->user()->password)) {
            throw ValidationException::withMessages([
                'password' => 'The password is incorrect.',
            ]);
        }
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }

    private function ensureTradingMember(): void
    {
        abort_unless(auth()->check() && auth()->user()->isTradingMember(), 403);
    }

    private function ensureRecordingManager(): void
    {
        abort_unless(auth()->check() && ($this->currentUserIsAdmin() || auth()->user()->isTradingLeader()), 403);
    }

    private function ensureCanManageRecording(TradingRecording $recording): void
    {
        abort_unless(auth()->check(), 403);

        if ($this->currentUserIsAdmin()) {
            return;
        }

        abort_unless(auth()->user()->isTradingLeader() && (int) $recording->uploaded_by === (int) auth()->id(), 403);
    }

    private function currentUserIsAdmin(): bool
    {
        return auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true);
    }

    private function ensureActiveRecording(TradingRecording $recording): void
    {
        abort_unless($recording->status && $recording->approval_status === 'approved', 404);
    }

    private function visibleRecordingsFor(User $user)
    {
        $leaderId = $this->leaderUplineId($user);

        return TradingRecording::where('status', true)
            ->where('approval_status', 'approved')
            ->where(function ($query) use ($leaderId): void {
                $query->whereNull('uploaded_by')
                    ->orWhereHas('uploader', fn ($uploader) => $uploader->whereIn('role_id', [1, 2]));

                if ($leaderId) {
                    $query->orWhere('uploaded_by', $leaderId);
                }
            });
    }

    private function ensureRecordingVisibleToUser(TradingRecording $recording, User $user): void
    {
        abort_unless($this->visibleRecordingsFor($user)->where('id', $recording->id)->exists(), 404);
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
