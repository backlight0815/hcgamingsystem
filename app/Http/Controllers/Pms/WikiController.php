<?php

namespace App\Http\Controllers\Pms;

use App\Http\Controllers\Controller;
use App\Models\PmProject;
use App\Models\PmWikiPage;
use App\Models\PmWikiSpace;
use App\Models\PmWorkItem;
use App\Models\User;
use App\Services\PmsAttachmentService;
use App\Support\PmsAccess;
use App\Support\PmsTicketAccess;
use App\Support\PmsWikiAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WikiController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(PmsAccess::workspace($request->user()), 403);
        $spaces = PmWikiSpace::with('owner:id,name')->orderBy('name')->get()
            ->filter(fn ($space) => PmsWikiAccess::viewSpace($request->user(), $space));
        $spaces->each(function ($space) use ($request): void {
            $space->pages_count = $space->pages()->with('project')->get()
                ->filter(fn ($page) => PmsWikiAccess::viewPage($request->user(), $page))->count();
        });
        $query = PmWikiPage::with(['space:id,key,name,visibility,owner_id', 'author:id,name', 'project:id,key,name'])
            ->whereIn('space_id', $spaces->pluck('id'));
        if (! PmsAccess::administrator($request->user())) {
            $ownedSpaceIds = $spaces->where('owner_id', $request->user()->id)->pluck('id');
            $query->where(fn ($q) => $q->where('status', 'published')->orWhere('author_id', $request->user()->id)->orWhereIn('space_id', $ownedSpaceIds));
            $projectIds = DB::table('pm_project_members')->where('user_id', $request->user()->id)->pluck('project_id');
            $query->where(fn ($q) => $q->whereNull('project_id')->orWhere('access_scope', 'workspace')
                ->orWhereIn('project_id', $projectIds));
        }
        if ($request->filled('q')) {
            $term = mb_substr(trim((string) $request->q), 0, 120);
            $query->where(fn ($q) => $q->where('title', 'like', '%'.$term.'%')->orWhere('content', 'like', '%'.$term.'%')
                ->orWhere('summary', 'like', '%'.$term.'%')->orWhere('tags', 'like', '%'.$term.'%')
                ->orWhereHas('author', fn ($author) => $author->where('name', 'like', '%'.$term.'%')));
        }
        if ($request->filled('space')) {
            $query->where('space_id', $request->integer('space'));
        }
        if ($request->filled('category')) $query->where('category', $request->input('category'));
        if ($request->filled('project')) $query->where('project_id', $request->integer('project'));
        if ($request->input('view') === 'mine') {
            $query->where('author_id', $request->user()->id);
        } elseif ($request->input('view') === 'drafts') {
            $query->where('status', 'draft');
        } elseif ($request->input('view') === 'favorites') {
            $query->whereIn('id', DB::table('pm_wiki_favorites')->where('user_id', $request->user()->id)->select('page_id'));
        }
        $pages = $query->orderByDesc('updated_at')->paginate(20)->withQueryString();
        $categories = PmWikiPage::CATEGORIES;
        $workspaceProjectIds = PmWikiPage::whereIn('space_id', $spaces->pluck('id'))
            ->where('access_scope', 'workspace')->where('status', 'published')->whereNotNull('project_id')->pluck('project_id');
        $projects = PmProject::orderBy('name')->get()->filter(fn ($project) => PmsAccess::allows($request->user(), $project, 'view')
            || $workspaceProjectIds->contains($project->id));
        return $request->expectsJson() ? response()->json(compact('spaces', 'pages', 'categories', 'projects'))
            : view('pms.wiki.index', compact('spaces', 'pages', 'categories', 'projects'));
    }

    public function storeSpace(Request $request)
    {
        abort_unless(PmsAccess::workspace($request->user()), 403);
        $data = $request->validate(['name' => 'required|string|max:255', 'key' => 'required|alpha_dash|max:32|unique:pm_wiki_spaces,key', 'description' => 'nullable|string|max:5000', 'visibility' => ['required', Rule::in(['workspace', 'restricted'])]]);
        $space = PmWikiSpace::create($data + ['owner_id' => $request->user()->id]);
        return $request->expectsJson() ? response()->json($space, 201) : redirect()->route('pms.wiki.spaces.show', $space)->with('success', 'Space created.');
    }

    public function showSpace(Request $request, PmWikiSpace $space)
    {
        abort_unless(PmsWikiAccess::viewSpace($request->user(), $space), 404);
        $pages = $space->pages()->with(['author:id,name', 'project:id,key,name'])->orderBy('title')->get()
            ->filter(fn ($page) => PmsWikiAccess::viewPage($request->user(), $page));
        $members = DB::table('pm_wiki_space_members')->join('users', 'users.id', '=', 'pm_wiki_space_members.user_id')
            ->where('space_id', $space->id)->get(['users.name', 'pm_wiki_space_members.role']);
        return $request->expectsJson() ? response()->json(compact('space', 'pages', 'members')) : view('pms.wiki.space', compact('space', 'pages', 'members'));
    }

    public function addMember(Request $request, PmWikiSpace $space)
    {
        abort_unless(PmsWikiAccess::manage($request->user(), $space), 403);
        $data = $request->validate(['user_id' => 'required|integer|exists:users,id', 'role' => ['required', Rule::in(['viewer', 'editor'])]]);
        abort_unless(PmsAccess::workspace(User::find($data['user_id'])), 422);
        DB::table('pm_wiki_space_members')->updateOrInsert(['space_id' => $space->id, 'user_id' => $data['user_id']], ['role' => $data['role'], 'created_at' => now(), 'updated_at' => now()]);
        return $request->expectsJson() ? response()->json(['added' => true]) : back()->with('success', 'Space member saved.');
    }

    public function create(Request $request)
    {
        abort_unless(PmsAccess::workspace($request->user()), 403);
        $templateText = match ($request->input('template')) {
            'procedure' => "# Purpose\n\nDescribe the outcome.\n\n## Prerequisites\n\n- [ ] Access confirmed\n\n## Steps\n\n1. First step\n\n## Verification\n\nHow to confirm success.\n",
            'api' => "# Overview\n\n## Endpoint\n\n`GET /api/example`\n\n## Authentication\n\n## Request\n\n## Response\n\n```json\n{}\n```\n\n## Errors\n",
            'incident' => "# Summary\n\n## Impact\n\n## Timeline\n\n## Root cause\n\n## Resolution\n\n## Follow-up actions\n\n- [ ] Action owner and due date\n",
            default => '',
        };
        $spaces = PmWikiSpace::orderBy('name')->get()->filter(fn ($space) => PmsWikiAccess::editSpace($request->user(), $space));
        $parents = PmWikiPage::with(['space', 'project'])->whereIn('space_id', $spaces->pluck('id'))->orderBy('title')->get()
            ->filter(fn ($page) => PmsWikiAccess::viewPage($request->user(), $page));
        $projects = PmProject::orderBy('name')->get()->filter(fn ($project) => PmsAccess::allows($request->user(), $project, 'view'));
        return view('pms.wiki.edit', compact('spaces', 'parents', 'templateText', 'projects'));
    }

    public function preview(Request $request)
    {
        abort_unless(PmsAccess::workspace($request->user()), 403);
        $data = $request->validate(['content' => 'nullable|string|max:200000']);

        return response()->json(['html' => PmWikiPage::renderContent($data['content'] ?? '')]);
    }

    public function storePage(Request $request)
    {
        $data = $this->pageData($request, false);
        $space = PmWikiSpace::findOrFail($data['space_id']);
        abort_unless(PmsWikiAccess::editSpace($request->user(), $space), 403);
        $this->validateParent($data['parent_id'] ?? null, $space->id, null, $data['project_id'] ?? null);
        $page = DB::transaction(function () use ($request, $data): PmWikiPage {
            $base = Str::slug($data['title']) ?: 'page';
            $slug = $base;
            for ($suffix = 2; PmWikiPage::withTrashed()->where('space_id', $data['space_id'])->where('slug', $slug)->exists(); $suffix++) {
                $slug = mb_substr($base, 0, 165).'-'.$suffix;
            }
            $page = PmWikiPage::create($data + ['slug' => $slug, 'author_id' => $request->user()->id,
                'last_editor_id' => $request->user()->id, 'lock_version' => 1, 'revision_number' => 1,
                'published_at' => $data['status'] === 'published' ? now() : null]);
            $this->revision($page, $request->user());
            return $page;
        });
        return $request->expectsJson() ? response()->json($page, 201) : redirect()->route('pms.wiki.pages.show', $page)->with('success', 'Page created.');
    }

    public function showPage(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::viewPage($request->user(), $page), 404);
        $page->load(['space', 'parent', 'project:id,key,name', 'author:id,name', 'editor:id,name']);
        DB::table('pm_wiki_pages')->where('id', $page->id)->increment('view_count');
        $children = $page->children()->get()->filter(fn ($child) => PmsWikiAccess::viewPage($request->user(), $child));
        $comments = DB::table('pm_wiki_comments')->join('users', 'users.id', '=', 'pm_wiki_comments.author_id')
            ->where('page_id', $page->id)->whereNull('pm_wiki_comments.deleted_at')->orderBy('pm_wiki_comments.created_at')
            ->get(['pm_wiki_comments.*', 'users.name as author_name']);
        $attachments = DB::table('pm_wiki_attachments')->where('page_id', $page->id)->whereNull('deleted_at')->orderBy('id')->get();
        $links = DB::table('pm_wiki_links')->where('page_id', $page->id)->get();
        $tickets = PmWorkItem::whereIn('id', $links->pluck('issue_id')->filter())->get()->filter(fn ($ticket) => PmsTicketAccess::view($request->user(), $ticket));
        $projects = PmProject::whereIn('id', $links->pluck('project_id')->filter())->get()->filter(fn ($project) => PmsAccess::allows($request->user(), $project, 'view'));
        if ($page->project && PmsAccess::allows($request->user(), $page->project, 'view')
            && ! $projects->contains('id', $page->project->id)) $projects->prepend($page->project);
        $favorite = DB::table('pm_wiki_favorites')->where('page_id', $page->id)->where('user_id', $request->user()->id)->exists();
        $relatedPages = PmWikiPage::with(['space', 'project'])->where('id', '<>', $page->id)
            ->where('space_id', $page->space_id)->where('status', 'published')
            ->where('project_id', $page->project_id)
            ->orderByRaw('CASE WHEN category = ? THEN 0 ELSE 1 END', [$page->category ?? ''])
            ->orderByDesc('updated_at')->limit(8)->get()
            ->filter(fn ($related) => PmsWikiAccess::viewPage($request->user(), $related))->take(4);
        $breadcrumbs = collect();
        $seen = [];
        for ($parent = $page->parent; $parent && !isset($seen[$parent->id]); $parent = $parent->parent) {
            $seen[$parent->id] = true;
            $breadcrumbs->prepend($parent);
        }
        return $request->expectsJson() ? response()->json(compact('page', 'children', 'comments', 'attachments', 'favorite', 'relatedPages'))
            : view('pms.wiki.page', compact('page', 'children', 'comments', 'attachments', 'tickets', 'projects', 'favorite', 'breadcrumbs', 'relatedPages'));
    }

    public function edit(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::editPage($request->user(), $page), 403);
        $spaces = PmWikiSpace::whereKey($page->space_id)->get();
        $parents = $page->space->pages()->with('project')->where('id', '<>', $page->id)->orderBy('title')->get()
            ->filter(fn ($candidate) => PmsWikiAccess::viewPage($request->user(), $candidate));
        $projects = PmProject::orderBy('name')->get()->filter(fn ($project) => PmsAccess::allows($request->user(), $project, 'view'));
        return view('pms.wiki.edit', compact('page', 'spaces', 'parents', 'projects'));
    }

    public function updatePage(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::editPage($request->user(), $page), 403);
        $data = $this->pageData($request, true, $page);
        abort_unless((int) $data['space_id'] === $page->space_id, 422);
        $this->validateParent($data['parent_id'] ?? null, $page->space_id, $page->id, $data['project_id'] ?? null);
        DB::transaction(function () use ($page, $data, $request): void {
            $locked = PmWikiPage::lockForUpdate()->findOrFail($page->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This page changed. Reload it before editing.']);
            }
            unset($data['lock_version']);
            if ($data['status'] === 'published' && ! $locked->published_at) {
                $data['published_at'] = now();
            }
            $locked->fill($data);
            $locked->last_editor_id = $request->user()->id;
            $locked->lock_version++;
            $locked->revision_number++;
            $locked->save();
            $this->revision($locked, $request->user());
        });
        return $request->expectsJson() ? response()->json($page->fresh()) : redirect()->route('pms.wiki.pages.show', $page)->with('success', 'Page updated.');
    }

    public function revisions(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::viewPage($request->user(), $page), 404);
        $revisions = DB::table('pm_wiki_revisions')->leftJoin('users', 'users.id', '=', 'pm_wiki_revisions.editor_id')
            ->where('page_id', $page->id)->orderByDesc('version')->select('pm_wiki_revisions.*', 'users.name as editor_name')->paginate(20);
        return $request->expectsJson() ? response()->json($revisions) : view('pms.wiki.revisions', compact('page', 'revisions'));
    }

    public function restore(Request $request, PmWikiPage $page, int $version)
    {
        abort_unless(PmsWikiAccess::editPage($request->user(), $page), 403);
        $data = $request->validate(['lock_version' => 'required|integer|min:1']);
        DB::transaction(function () use ($request, $page, $version, $data): void {
            $locked = PmWikiPage::lockForUpdate()->findOrFail($page->id);
            if ((int) $locked->lock_version !== (int) $data['lock_version']) {
                throw ValidationException::withMessages(['lock_version' => 'This page changed. Reload it before restoring.']);
            }
            $revision = DB::table('pm_wiki_revisions')->where('page_id', $page->id)->where('version', $version)->first();
            abort_unless($revision, 404);
            if ((int) $revision->project_id !== (int) $locked->project_id) {
                throw ValidationException::withMessages(['project_id' => 'A revision cannot change the page project.']);
            }
            $locked->fill(['title' => $revision->title, 'content' => $revision->content,
                'category' => $revision->category, 'audience' => $revision->audience, 'summary' => $revision->summary,
                'tags' => json_decode($revision->tags ?? '[]', true), 'status' => $revision->status,
                'last_editor_id' => $request->user()->id]);
            $locked->lock_version++;
            $locked->revision_number++;
            $locked->save();
            $this->revision($locked, $request->user());
        });
        return $request->expectsJson() ? response()->json($page->fresh()) : redirect()->route('pms.wiki.pages.show', $page)->with('success', 'Revision restored as a new version.');
    }

    public function destroyPage(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsAccess::administrator($request->user()), 403);
        $page->delete();
        return $request->expectsJson() ? response()->json(['deleted' => true]) : redirect()->route('pms.wiki.spaces.show', $page->space)->with('success', 'Page archived.');
    }

    public function comment(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::viewPage($request->user(), $page), 403);
        $data = $request->validate(['body' => 'required|string|max:30000']);
        $id = DB::table('pm_wiki_comments')->insertGetId(['page_id' => $page->id, 'author_id' => $request->user()->id, 'body' => $data['body'], 'created_at' => now(), 'updated_at' => now()]);
        return $request->expectsJson() ? response()->json(['id' => $id], 201) : back()->with('success', 'Comment added.');
    }

    public function favorite(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::viewPage($request->user(), $page), 403);
        $query = DB::table('pm_wiki_favorites')->where('page_id', $page->id)->where('user_id', $request->user()->id);
        if ($query->exists()) {
            $query->delete();
            $favorite = false;
        } else {
            DB::table('pm_wiki_favorites')->insert(['page_id' => $page->id, 'user_id' => $request->user()->id, 'created_at' => now()]);
            $favorite = true;
        }
        return $request->expectsJson() ? response()->json(compact('favorite')) : back();
    }

    public function link(Request $request, PmWikiPage $page)
    {
        abort_unless(PmsWikiAccess::editPage($request->user(), $page), 403);
        $data = $request->validate(['ticket_key' => 'nullable|required_without:project_id|string|max:48', 'project_id' => 'nullable|required_without:ticket_key|integer|exists:pm_projects,id']);
        if (filled($data['ticket_key'] ?? null) && filled($data['project_id'] ?? null)) {
            throw ValidationException::withMessages(['ticket_key' => 'Link one ticket or one project at a time.']);
        }
        $issue = filled($data['ticket_key'] ?? null) ? PmWorkItem::where('issue_key', strtoupper(trim($data['ticket_key'])))
            ->orWhere('reference', strtolower(trim($data['ticket_key'])))->firstOrFail() : null;
        $project = !empty($data['project_id']) ? PmProject::findOrFail($data['project_id']) : null;
        if ($issue) abort_unless(PmsTicketAccess::view($request->user(), $issue), 403);
        if ($project) abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        if ($page->project_id && (($project && (int) $project->id !== (int) $page->project_id)
            || ($issue && (int) $issue->project_id !== (int) $page->project_id))) {
            throw ValidationException::withMessages(['project_id' => 'A project page can only reference work in its own project.']);
        }
        DB::table('pm_wiki_links')->insertOrIgnore(['page_id' => $page->id, 'issue_id' => $issue?->id, 'project_id' => $project?->id, 'created_by' => $request->user()->id, 'created_at' => now()]);
        return $request->expectsJson() ? response()->json(['linked' => true]) : back()->with('success', 'Documentation reference linked.');
    }

    public function upload(Request $request, PmWikiPage $page, PmsAttachmentService $attachments)
    {
        abort_unless(PmsWikiAccess::editPage($request->user(), $page), 403);
        $request->validate(['file' => 'required|file']);
        $file = $request->file('file');
        $attachments->validateFiles([$file]);
        $path = $file->store('pms/wiki/'.$page->id, 'local');
        if (!$path) throw ValidationException::withMessages(['file' => 'The file could not be stored.']);
        try {
            $id = DB::table('pm_wiki_attachments')->insertGetId([
                'page_id' => $page->id, 'uploaded_by' => $request->user()->id,
                'original_name' => mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', basename($file->getClientOriginalName())), 0, 255),
                'storage_path' => $path, 'extension' => strtolower($file->getClientOriginalExtension()),
                'mime_type' => $file->getMimeType(), 'size_bytes' => $file->getSize(),
                'sha256' => hash_file('sha256', $file->getRealPath()), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
        return $request->expectsJson() ? response()->json(['id' => $id], 201) : back()->with('success', 'File attached.');
    }

    public function download(Request $request, PmWikiPage $page, int $attachment)
    {
        abort_unless(PmsWikiAccess::viewPage($request->user(), $page), 404);
        $file = DB::table('pm_wiki_attachments')->where('id', $attachment)->where('page_id', $page->id)->whereNull('deleted_at')->first();
        abort_unless($file && Storage::disk('local')->exists($file->storage_path), 404);
        return Storage::disk('local')->download($file->storage_path, $file->original_name, [
            'Content-Type' => $file->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "sandbox; default-src 'none'", 'Cache-Control' => 'private, no-store',
        ]);
    }

    private function pageData(Request $request, bool $editing, ?PmWikiPage $page = null): array
    {
        $data = $request->validate([
            'space_id' => 'required|integer|exists:pm_wiki_spaces,id', 'parent_id' => 'nullable|integer|exists:pm_wiki_pages,id',
            'title' => 'required|string|max:255', 'content' => 'nullable|string|max:200000',
            'summary' => 'nullable|string|max:500', 'audience' => 'nullable|string|max:255',
            'category' => ['nullable', Rule::in(PmWikiPage::CATEGORIES)],
            'project_id' => 'nullable|integer|exists:pm_projects,id',
            'tags' => 'nullable|string|max:500', 'status' => ['required', Rule::in(['draft', 'published'])],
            'lock_version' => $editing ? 'required|integer|min:1' : 'prohibited',
        ]);
        $data['project_id'] = $editing && ! array_key_exists('project_id', $data)
            ? $page?->project_id : ($data['project_id'] ?? null);
        if ($editing && $page?->project_id && (int) $data['project_id'] !== (int) $page->project_id) {
            throw ValidationException::withMessages(['project_id' => 'A project-specific page cannot be moved or made workspace-wide.']);
        }
        if ($data['project_id']) {
            $project = PmProject::findOrFail($data['project_id']);
            abort_unless(PmsAccess::allows($request->user(), $project, 'view'), 403);
        }
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))->map(fn ($tag) => trim($tag))->filter()->unique()->take(15)->values()->all();
        return $data;
    }

    private function validateParent(?int $parentId, int $spaceId, ?int $pageId = null, ?int $projectId = null): void
    {
        if (!$parentId) return;
        $parent = PmWikiPage::findOrFail($parentId);
        if ($parent->space_id !== $spaceId) throw ValidationException::withMessages(['parent_id' => 'Parent page must be in this space.']);
        abort_unless(PmsWikiAccess::viewPage(auth()->user(), $parent), 403);
        if ($parent->project_id && (int) $parent->project_id !== (int) $projectId) {
            throw ValidationException::withMessages(['parent_id' => 'A parent page must belong to the same project.']);
        }
        $seen = [];
        for ($cursor = $parent; $cursor && !isset($seen[$cursor->id]); $cursor = $cursor->parent) {
            if ($cursor->id === $pageId) throw ValidationException::withMessages(['parent_id' => 'Circular page hierarchy is not allowed.']);
            $seen[$cursor->id] = true;
        }
    }

    private function revision(PmWikiPage $page, User $editor): void
    {
        DB::table('pm_wiki_revisions')->insert([
            'page_id' => $page->id, 'version' => $page->revision_number,
            'title' => $page->title, 'project_id' => $page->project_id,
            'category' => $page->category, 'audience' => $page->audience, 'summary' => $page->summary,
            'content' => $page->content, 'tags' => json_encode($page->tags),
            'status' => $page->status, 'status_code' => $page->status_code,
            'editor_id' => $editor->id, 'created_at' => now(),
        ]);
    }
}
