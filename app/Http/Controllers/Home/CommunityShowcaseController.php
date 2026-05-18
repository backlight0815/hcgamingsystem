<?php

namespace App\Http\Controllers\Home;

use App\Http\Controllers\Controller;
use App\Models\CommunityShowcasePage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class CommunityShowcaseController extends Controller
{
    public function show()
    {
        $page = CommunityShowcasePage::where('slug', CommunityShowcasePage::DEFAULT_SLUG)
            ->where('is_published', true)
            ->firstOrFail();

        return view('frontend.community_showcase', compact('page'));
    }

    public function edit()
    {
        $this->ensureAdmin();

        $page = CommunityShowcasePage::firstOrCreate(
            ['slug' => CommunityShowcasePage::DEFAULT_SLUG],
            CommunityShowcasePage::defaultContent()
        );

        $breadcrumbData = [
            ['label' => 'HC Gaming', 'url' => route('all.statistics')],
            ['label' => 'Community Showcase', 'url' => route('admin.community.showcase.edit')],
        ];

        return view('admin.community_showcase.edit', compact('page', 'breadcrumbData'));
    }

    public function update(Request $request)
    {
        $this->ensureAdmin();

        $page = CommunityShowcasePage::firstOrCreate(
            ['slug' => CommunityShowcasePage::DEFAULT_SLUG],
            CommunityShowcasePage::defaultContent()
        );

        $data = $request->validate([
            'hero_kicker' => ['nullable', 'string', 'max:255'],
            'hero_title' => ['required', 'string', 'max:255'],
            'hero_subtitle' => ['nullable', 'string', 'max:255'],
            'hero_intro' => ['nullable', 'string', 'max:1200'],
            'poster_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:4096'],
            'primary_cta_label' => ['nullable', 'string', 'max:80'],
            'primary_cta_url' => ['nullable', 'string', 'max:255'],
            'secondary_cta_label' => ['nullable', 'string', 'max:80'],
            'secondary_cta_url' => ['nullable', 'string', 'max:255'],
            'entry_requirements' => ['nullable', 'array'],
            'entry_requirements.*.label' => ['nullable', 'string', 'max:120'],
            'entry_requirements.*.value' => ['nullable', 'string', 'max:80'],
            'entry_requirements.*.description' => ['nullable', 'string', 'max:800'],
            'core_services' => ['nullable', 'array'],
            'core_services.*.title' => ['nullable', 'string', 'max:160'],
            'core_services.*.description' => ['nullable', 'string', 'max:800'],
            'secondary_services' => ['nullable', 'array'],
            'secondary_services.*.title' => ['nullable', 'string', 'max:160'],
            'secondary_services.*.description' => ['nullable', 'string', 'max:800'],
            'service_principle' => ['nullable', 'string', 'max:1600'],
            'risk_disclaimer' => ['nullable', 'string', 'max:1600'],
            'is_published' => ['nullable', Rule::in(['1'])],
        ]);

        $data['entry_requirements'] = $this->normalizeRequirements($request->input('entry_requirements', []));
        $data['core_services'] = $this->normalizeServices($request->input('core_services', []));
        $data['secondary_services'] = $this->normalizeServices($request->input('secondary_services', []));
        $data['is_published'] = $request->boolean('is_published');

        if ($request->hasFile('poster_image')) {
            $data['poster_image'] = $this->storePosterImage($request, $page->poster_image);
        }

        $page->update($data);

        return redirect()
            ->route('admin.community.showcase.edit')
            ->with([
                'message' => 'Community showcase page updated successfully.',
                'alert-type' => 'success',
            ]);
    }

    private function normalizeRequirements(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'label' => trim((string) ($item['label'] ?? '')),
                'value' => trim((string) ($item['value'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['label'] !== '' || $item['value'] !== '' || $item['description'] !== '')
            ->values()
            ->all();
    }

    private function normalizeServices(array $items): array
    {
        return collect($items)
            ->map(fn (array $item): array => [
                'title' => trim((string) ($item['title'] ?? '')),
                'description' => trim((string) ($item['description'] ?? '')),
            ])
            ->filter(fn (array $item): bool => $item['title'] !== '' || $item['description'] !== '')
            ->values()
            ->all();
    }

    private function storePosterImage(Request $request, ?string $oldPath): string
    {
        $image = $request->file('poster_image');
        $fileName = uniqid('community_showcase_', true) . '.' . $image->getClientOriginalExtension();
        $destination = public_path('upload/community_showcase');

        if (! File::exists($destination)) {
            File::makeDirectory($destination, 0755, true);
        }

        $image->move($destination, $fileName);

        if ($oldPath && str_starts_with($oldPath, 'upload/community_showcase/') && File::exists(public_path($oldPath))) {
            File::delete(public_path($oldPath));
        }

        return 'upload/community_showcase/' . $fileName;
    }

    private function ensureAdmin(): void
    {
        abort_unless(auth()->check() && in_array((int) auth()->user()->role_id, [1, 2], true), 403);
    }
}
