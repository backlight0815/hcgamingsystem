<?php

namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use App\Models\MarketingResource;
use App\Services\AppNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class MarketingResourceController extends Controller
{
    private const VIEWER_ROLES = [760, 770];
    private const ADMIN_ROLES = [1, 2];

    public function adminIndex(Request $request)
    {
        $this->ensureAdmin();

        $search = trim((string) $request->input('search'));
        $status = $request->input('status');

        $resourcesQuery = MarketingResource::with('uploader')->latest();

        if ($search !== '') {
            $resourcesQuery->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        if ($status !== null && $status !== '') {
            $resourcesQuery->where('status', (bool) $status);
        }

        $resources = $resourcesQuery->paginate(15)->withQueryString();
        $totalResources = MarketingResource::count();
        $activeResources = MarketingResource::where('status', true)->count();
        $totalDownloads = MarketingResource::sum('download_count');

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Marketing Resources', 'url' => route('admin.marketing.resources.index')],
        ];

        return view('admin.marketing_resources.index', compact(
            'resources',
            'search',
            'status',
            'totalResources',
            'activeResources',
            'totalDownloads',
            'breadcrumbData'
        ));
    }

    public function create()
    {
        $this->ensureAdmin();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Marketing Resources', 'url' => route('admin.marketing.resources.index')],
            ['label' => 'Upload Resource', 'url' => route('admin.marketing.resources.create')],
        ];

        return view('admin.marketing_resources.create', compact('breadcrumbData'));
    }

    public function store(Request $request)
    {
        $this->ensureAdmin();

        $data = $this->validatedResourceData($request);
        $fileData = $this->storeResourceFile($request);

        $resource = MarketingResource::create(array_merge($data, $fileData, [
            'uploaded_by' => auth()->id(),
        ]));

        if ($resource->status) {
            AppNotificationService::notifyRoles(
                self::VIEWER_ROLES,
                'New marketing resource uploaded',
                $resource->title . ' is now available for leaders and recruiters.',
                route('marketing.resources.index'),
                'marketing_resource'
            );
        }

        return redirect()
            ->route('admin.marketing.resources.index')
            ->with('success', 'Marketing resource uploaded successfully.');
    }

    public function edit(MarketingResource $resource)
    {
        $this->ensureAdmin();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Marketing Resources', 'url' => route('admin.marketing.resources.index')],
            ['label' => 'Edit Resource', 'url' => route('admin.marketing.resources.edit', $resource->id)],
        ];

        return view('admin.marketing_resources.edit', compact('resource', 'breadcrumbData'));
    }

    public function update(Request $request, MarketingResource $resource)
    {
        $this->ensureAdmin();

        $data = $this->validatedResourceData($request, false);

        if ($request->hasFile('material_file')) {
            $this->deleteResourceFile($resource);
            $data = array_merge($data, $this->storeResourceFile($request));
        }

        $resource->update($data);

        $resource = $resource->fresh();

        if ($resource->status) {
            AppNotificationService::notifyRoles(
                self::VIEWER_ROLES,
                'Marketing resource updated',
                $resource->title . ' has been updated by administration.',
                route('marketing.resources.index'),
                'marketing_resource'
            );
        }

        return redirect()
            ->route('admin.marketing.resources.index')
            ->with('success', 'Marketing resource updated successfully.');
    }

    public function destroy(MarketingResource $resource)
    {
        $this->ensureAdmin();
        $this->deleteResourceFile($resource);
        $resource->delete();

        return redirect()
            ->route('admin.marketing.resources.index')
            ->with('success', 'Marketing resource deleted successfully.');
    }

    public function adminDownload(MarketingResource $resource)
    {
        $this->ensureAdmin();
        $this->ensureStoredResourceExists($resource);

        $resource->increment('download_count');

        return Storage::disk('local')->download(
            $resource->file_path,
            $resource->original_filename
        );
    }

    public function leaderIndex(Request $request)
    {
        $this->ensureMarketingViewer();

        $search = trim((string) $request->input('search'));

        $resourcesQuery = MarketingResource::query()
            ->where('status', true)
            ->latest();

        if ($search !== '') {
            $resourcesQuery->where(function ($query) use ($search): void {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $resources = $resourcesQuery->paginate(12)->withQueryString();

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Marketing Resources', 'url' => route('marketing.resources.index')],
        ];

        return view('marketing_resources.index', compact('resources', 'search', 'breadcrumbData'));
    }

    public function view(Request $request, MarketingResource $resource)
    {
        $this->ensureMarketingViewer();
        $this->ensureActiveResource($resource);
        $this->validateCurrentPassword($request);
        $this->ensureStoredResourceExists($resource);

        $response = response()->file(Storage::disk('local')->path($resource->file_path), [
            'Content-Type' => $resource->mime_type ?: 'application/octet-stream',
        ]);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $resource->original_filename
        );

        return $response;
    }

    public function download(Request $request, MarketingResource $resource)
    {
        $this->ensureMarketingViewer();
        $this->ensureActiveResource($resource);

        try {
            $this->validateCurrentPassword($request);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('marketing.resources.index')
                ->withErrors($exception->errors());
        }

        $this->ensureStoredResourceExists($resource);
        $resource->increment('download_count');

        return Storage::disk('local')->download(
            $resource->file_path,
            $resource->original_filename
        );
    }

    private function validatedResourceData(Request $request, bool $fileRequired = true): array
    {
        $fileRules = [$fileRequired ? 'required' : 'nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpeg,jpg,png,webp,zip', 'max:51200'];

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'status' => ['nullable', 'boolean'],
            'material_file' => $fileRules,
        ]);

        $data['status'] = filter_var($request->input('status', true), FILTER_VALIDATE_BOOLEAN);
        unset($data['material_file']);

        return $data;
    }

    private function storeResourceFile(Request $request): array
    {
        $file = $request->file('material_file');
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
        $filePath = $file->storeAs('marketing_resources', $fileName, 'local');

        return [
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
        ];
    }

    private function deleteResourceFile(MarketingResource $resource): void
    {
        if ($resource->file_path && Storage::disk('local')->exists($resource->file_path)) {
            Storage::disk('local')->delete($resource->file_path);
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
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, self::ADMIN_ROLES, true), 403);
    }

    private function ensureMarketingViewer(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, array_merge(self::ADMIN_ROLES, self::VIEWER_ROLES), true), 403);
    }

    private function ensureActiveResource(MarketingResource $resource): void
    {
        abort_unless($resource->status, 404);
    }

    private function ensureStoredResourceExists(MarketingResource $resource): void
    {
        abort_unless($resource->file_path && Storage::disk('local')->exists($resource->file_path), 404);
    }
}
