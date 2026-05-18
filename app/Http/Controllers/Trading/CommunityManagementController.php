<?php
namespace App\Http\Controllers\Trading;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Community;
use App\Models\CommunityDocument;
use App\Models\CommunityTPSetting;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;


class CommunityManagementController extends Controller
{
    // ✅ List all communities
    public function index()
    {
        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Community Management', 'url' => ''] // current page
        ];

        $communities = Community::all();

        
    // 🔥 Add statistics
    $totalCommunity = Community::count();
    $totalActive = Community::where('status', 1)->count();
    $totalInactive = Community::where('status', 0)->count();


  return view('admin.community.community_all', compact(
        'communities',
        'breadcrumbData',
        'totalCommunity',
        'totalActive',
        'totalInactive'
    ));
    }
// ✅ Show form to create new community
public function create()
{
  

    // Pass to view, initialize empty tag for create form
    $community_tag = '';

    return view('admin.community.community_add', compact('community_tag'));
}
    public function store(Request $request)
{
    // 1️⃣ Validate input
    $request->validate([
        'name' => 'required|string|max:100',
        'discord_webhook' => 'required|url',
        'discord_webhook_signal' => 'nullable|url',
        'discord_webhook_outlook' => 'nullable|url',
        'discord_webhook_knowledge' => 'nullable|url',
        'discord_webhook_news'=>'nullable|url',
        'category' => 'required|in:public,executive,test',
        'community_tag' => 'nullable|string|max:255', // new tag field
    ]);

    // 2️⃣ Create community
    Community::create($request->only(
        'name', 
        'category',
        'discord_webhook',
        'discord_webhook_signal',
        'discord_webhook_outlook',
        'discord_webhook_knowledge',
        'discord_webhook_news',
        'status',
        'community_tag' // include tag
    ));

    // 3️⃣ Redirect with success message
    return redirect()->route('communities.index')->with([
        'message' => 'Community added successfully!',
        'alert-type' => 'success'
    ]);
}



    // ✅ Show form to edit community
    public function edit($id)
    {
        $community = Community::findOrFail($id);

        
    

        return view('admin.community.community_edit', compact('community'));
    }
public function update(Request $request, $id)
{
    $request->validate([
        'name' => 'required|string|max:100',
        'discord_webhook' => 'required|url',
        'category' => 'required|in:public,executive,test',
        'status' => 'required|in:0,1',
        'community_tag' => 'nullable|string|max:255', // new tag field
    ], [
        'name.required' => 'Community Name is required',
        'discord_webhook.required' => 'Discord Webhook URL is required',
        'discord_webhook.url' => 'Invalid Webhook URL format',
        'status.required' => 'Status is required',
        'category.required' => 'Category is required',
        'status.in' => 'Invalid status selected',
    ]);

    Community::findOrFail($id)->update([
        'name' => $request->name,
        'discord_webhook' => $request->discord_webhook,
        'discord_webhook_signal' => $request->discord_webhook_signal,
        'discord_webhook_knowledge' => $request->discord_webhook_knowledge,
        'discord_webhook_news'=>$request->discord_webhook_news,
        'discord_webhook_outlook' => $request->discord_webhook_outlook,
        'category' => $request->category,
        'status' => $request->status,
        'community_tag' => $request->community_tag, // save tag
    ]);

    return redirect()->route('communities.index')->with([
        'message' => 'Community updated successfully!',
        'alert-type' => 'success'
    ]);
}


    // ✅ Delete community
    public function destroy($id)
    {
        $community = Community::findOrFail($id);
        $community->delete();

        return redirect()->route('communities.index')->with([
            'message' => 'Community deleted successfully!',
            'alert-type' => 'success'
        ]);
    }

    public function documentsIndex(Request $request)
    {
        $this->ensureFounderPartner();

        $communities = Community::withCount('documents')->orderBy('name')->get();
        $selectedCommunityId = $request->input('community_id');
        $search = trim((string) $request->input('search'));

        $documentsQuery = CommunityDocument::with(['community', 'uploader'])
            ->latest();

        if ($selectedCommunityId) {
            $documentsQuery->where('community_id', $selectedCommunityId);
        }

        if ($search !== '') {
            $documentsQuery->where(function ($query) use ($search) {
                $query
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('original_filename', 'like', "%{$search}%");
            });
        }

        $documents = $documentsQuery->paginate(12)->withQueryString();
        $totalDocuments = CommunityDocument::count();
        $totalCommunitiesWithDocuments = Community::has('documents')->count();
        $totalDownloads = CommunityDocument::sum('download_count');

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Community Management', 'url' => route('communities.index')],
            ['label' => 'Community Documents', 'url' => route('communities.documents.index')],
        ];

        return view('admin.community.documents', compact(
            'communities',
            'documents',
            'selectedCommunityId',
            'search',
            'totalDocuments',
            'totalCommunitiesWithDocuments',
            'totalDownloads',
            'breadcrumbData'
        ));
    }

    public function storeDocument(Request $request)
    {
        $this->ensureFounderPartner();
        $this->validateCurrentPassword($request);

        $data = $request->validate([
            'community_id' => ['required', 'exists:communities,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:3000'],
            'document' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,jpeg,jpg,png,webp', 'max:20480'],
        ]);

        $file = $request->file('document');
        $extension = strtolower($file->getClientOriginalExtension());
        $fileName = (string) Str::uuid() . ($extension ? ".{$extension}" : '');
        $filePath = $file->storeAs('community_documents', $fileName, 'local');

        CommunityDocument::create([
            'community_id' => $data['community_id'],
            'uploaded_by' => auth()->id(),
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'file_path' => $filePath,
            'original_filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize() ?: 0,
        ]);

        return redirect()
            ->route('communities.documents.index', ['community_id' => $data['community_id']])
            ->with('success', 'Community document uploaded successfully.');
    }

    public function viewDocument(Request $request, CommunityDocument $document)
    {
        $this->ensureFounderPartner();
        $this->validateCurrentPassword($request);
        $this->ensureStoredDocumentExists($document);

        $response = response()->file(Storage::disk('local')->path($document->file_path), [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
        ]);

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            $document->original_filename
        );

        return $response;
    }

    public function downloadDocument(Request $request, CommunityDocument $document)
    {
        $this->ensureFounderPartner();
        $this->validateCurrentPassword($request);
        $this->ensureStoredDocumentExists($document);

        $document->increment('download_count');

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename
        );
    }

    public function destroyDocument(Request $request, CommunityDocument $document)
    {
        $this->ensureFounderPartner();
        $this->validateCurrentPassword($request);

        if ($document->file_path && Storage::disk('local')->exists($document->file_path)) {
            Storage::disk('local')->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('communities.documents.index')
            ->with('success', 'Community document deleted successfully.');
    }

     public function tpSettingsDashboard()
    {
        // Load all communities with TP settings relationship
        $communities = Community::with('tpSettings')->get();

        return view('admin.community.tp_settings_dashboard', compact('communities'));
    }


    /**
     * Update TP Notification Settings for one community
     */
    public function updateTpSettingsDashboard(Request $request)
    {
        $selected = $request->selected_communities ?? [];

        foreach ($selected as $communityId) {
            for ($i = 1; $i <= 10; $i++) {
                $enabled = isset($request->tp[$communityId][$i]) ? 1 : 0;

                CommunityTPSetting::updateOrCreate(
                    ['community_id' => $communityId, 'tp_level' => $i],
                    ['enabled' => $enabled]
                );
            }
        }

        return redirect()->back()->with([
            'message' => 'TP Notification Settings updated successfully!',
            'alert-type' => 'success'
        ]);
    }

  public function updateEveryoneToggle(Request $request)
{
    $communityId = $request->input('community_id');
    $toggleValue = $request->input('discord_everyone_enabled', 0);

    $community = Community::findOrFail($communityId);
    $community->discord_everyone_enabled = $toggleValue;
    $community->save();

    return redirect()->back()->with([
        'message' => '"@everyone" setting updated successfully!',
        'alert-type' => 'success',
    ]);
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

private function ensureFounderPartner(): void
{
    abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
}

private function ensureStoredDocumentExists(CommunityDocument $document): void
{
    abort_unless($document->file_path && Storage::disk('local')->exists($document->file_path), 404);
}

}
