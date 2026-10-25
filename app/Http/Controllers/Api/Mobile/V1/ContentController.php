<?php

namespace App\Http\Controllers\Api\Mobile\V1;

use App\Models\KnowledgeCentre;
use App\Models\MarketAnalysis;
use App\Models\MarketingResource;
use App\Models\TradingBlog;
use App\Models\TradingPositionApplication;
use App\Models\TradingRecording;
use App\Models\TradingSignal;
use App\Models\User;
use App\Services\MarketOutlookPublicationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ContentController extends MobileController
{
    public function index(Request $request): JsonResponse
    {
        $role = (int) $request->user()->role_id;
        abort_unless(in_array($role, [1, 2, 501, 750, 760, 770], true), 403);

        $market = app(MarketOutlookPublicationService::class)->visible($request->user())
            ->limit(app(MarketOutlookPublicationService::class)->hasHistoryAccess($request->user()) ? 30 : 6)
            ->get();
        $blogs = in_array($role, [1, 2, 750, 760, 770], true)
            ? TradingBlog::with('author')->published()->latest('published_at')->limit(30)->get()
            : collect();
        $recordings = in_array($role, [750, 760, 770], true)
            ? $this->visibleRecordings($request->user())->withCount('materials')->latest()->limit(30)->get()
            : collect();
        $knowledge = in_array($role, [750, 760, 770], true)
            ? $this->visibleKnowledge($request->user())->with(['community', 'uploader'])->latest()->limit(50)->get()
            : collect();
        $marketing = in_array($role, [1, 2, 760, 770], true)
            ? MarketingResource::where('status', true)->latest()->limit(50)->get()
            : collect();

        $recordingItems = $recordings->map(function (TradingRecording $recording): array {
            return [
                'id' => (string) $recording->id,
                'title' => $recording->title,
                'description' => $recording->description,
                'source_name' => $recording->source_name,
                'status' => (bool) $recording->status,
                'approval_status' => $recording->approval_status,
                'created_at' => $recording->created_at,
                'requires_password' => true,
            ];
        });

        return $this->success([
            'signals' => TradingSignal::published()
                ->with(['user:id,name', 'community:id,name'])
                ->latest('published_at')
                ->limit(100)
                ->get(),
            'market_analyses' => $market,
            'featured_market_analysis' => $market->first(),
            'blogs' => $blogs,
            'featured_blog' => $blogs->firstWhere('is_featured', true),
            'recordings' => $recordingItems,
            'knowledge' => $knowledge,
            'marketing_resources' => $marketing,
        ], 'Trading hub content loaded.')->header('Cache-Control', 'private, no-store');
    }

    public function accessRecording(Request $request, TradingRecording $recording): JsonResponse
    {
        abort_unless(in_array((int) $request->user()->role_id, [750, 760, 770], true), 403);
        abort_unless($recording->status && $recording->approval_status === 'approved', 404);
        abort_unless($this->visibleRecordings($request->user())->where('id', $recording->id)->exists(), 404);
        $this->validatePassword($request);
        $recording->load('materials');

        return $this->success([
            'video_url' => $recording->video_url,
            'download_url' => $recording->effective_download_url,
            'materials' => $recording->materials()->get()->map(fn ($material): array => [
                'id' => (string) $material->id,
                'title' => $material->title,
                'original_filename' => $material->original_filename,
                'mime_type' => $material->mime_type,
                'file_size' => $material->file_size,
            ]),
        ], 'Recording access granted.');
    }

    public function accessMarketingResource(Request $request, MarketingResource $resource): JsonResponse
    {
        abort_unless(in_array((int) $request->user()->role_id, [1, 2, 760, 770], true), 403);
        abort_unless($resource->status, 404);
        $this->validatePassword($request);

        abort_unless($resource->file_path && Storage::disk('local')->exists($resource->file_path), 404);
        $token = Str::random(64);
        Cache::put('mobile_resource:'.hash('sha256', $token), $resource->id, now()->addSeconds(60));

        return $this->success([
            'view_url' => url('/mobile/resource/'.$token),
            'download_url' => url('/mobile/resource/'.$token),
            'expires_in' => 60,
        ], 'Resource access granted.');
    }

    public function consumeMarketingResource(string $token): BinaryFileResponse
    {
        $resourceId = Cache::pull('mobile_resource:'.hash('sha256', $token));
        abort_unless($resourceId, 410, 'This resource link has expired or has already been used.');

        $resource = MarketingResource::where('status', true)->findOrFail($resourceId);
        abort_unless($resource->file_path && Storage::disk('local')->exists($resource->file_path), 404);
        $resource->increment('download_count');

        return response()->file(Storage::disk('local')->path($resource->file_path), [
            'Content-Type' => $resource->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($resource->original_filename).'"',
        ]);
    }

    private function validatePassword(Request $request): void
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
    }

    private function visibleRecordings(User $user)
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

    private function visibleKnowledge(User $user)
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
            $current = User::find($current->invited_by);
            if (! $current) {
                return null;
            }
            if ((int) $current->role_id === TradingPositionApplication::ROLE_LEADERSHIP) {
                return (int) $current->id;
            }
        }

        return null;
    }
}
