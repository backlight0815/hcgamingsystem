<?php

namespace Database\Seeders;

use App\Models\PmProject;
use App\Models\PmWikiPage;
use App\Models\PmWikiSpace;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PmsQaLearningCenterSeeder extends Seeder
{
    private const ARTICLES = [
        ['pgos-overview', 'Understanding PGOS — Product Overview, Business Objectives & User Journey', 'PGOS Product Knowledge', 'QA Engineers, Business Analysts, Project Managers', 'Follow a complete PGOS goal journey and learn what a useful outcome looks like.'],
        ['pgos-testing', 'How to Test PGOS — QA Responsibilities, Test Coverage & Real-World Scenarios', 'QA Training & Best Practices', 'QA Engineers', 'Plan business-focused tests across goal creation, progress, reminders, reviews and offline use.'],
        ['pms-getting-started', 'Getting Started with the Project Management System (PMS)', 'PMS User Guide', 'All project team members', 'Join a project, find assigned work and use PMS as a new team member.'],
        ['pms-ticket-types', 'Understanding Project Tickets — Project, REQ, ENH, PRD & QAT', 'Ticket Management', 'QA Engineers, Business Analysts, Project Managers', 'Choose the right ticket type and connect work for traceability.'],
        ['pms-bug-reports', 'How to Create Professional Bug Reports and QAT Tickets', 'QA Training & Best Practices', 'QA Engineers', 'Write reproducible defects with expected results, evidence and appropriate priority.'],
        ['pms-status-priority', 'Understanding Ticket Status, Priorities, Blockers & Resolutions', 'Ticket Management', 'QA Engineers, Developers, Project Managers', 'Interpret the QAT lifecycle and communicate urgency, blockers and outcomes.'],
        ['pms-team-responsibilities', 'How to Manage Projects, Assignments & Team Responsibilities', 'Project & Release Management', 'Project Managers, Business Analysts, QA Engineers', 'Understand project membership, ticket ownership, assignment and coordinated delivery.'],
        ['pms-cycles-releases', 'Understanding Development Cycles, Release Management & QA Testing', 'Project & Release Management', 'QA Engineers, Project Managers, Business Analysts, Developers', 'Plan QA around cycles and releases and describe remaining release risk.'],
        ['pms-reviews-regression', 'How to Perform Requirement Reviews, Retesting & Regression Testing', 'QA Training & Best Practices', 'QA Engineers, Business Analysts, Project Managers', 'Turn unclear requirements into testable criteria, then retest and check related behavior.'],
        ['pms-collaboration', 'Professional Team Collaboration — Comments, Attachments, Ticket History & Knowledge Sharing', 'Team Collaboration', 'All project team members', 'Give clear updates, preserve evidence and share reusable decisions.'],
    ];

    public function run(): void
    {
        $key = trim((string) env('PMS_PGOS_PROJECT_KEY', 'PGOS'));
        $project = PmProject::where('key', $key)->first();
        if (! $project || ! preg_match('/PGOS|personal\s+goal/i', $project->name)) {
            throw new RuntimeException("PGOS project could not be verified by key '{$key}' and name. Set PMS_PGOS_PROJECT_KEY to the verified project key; no articles were added.");
        }
        if (! $project->owner_id) {
            throw new RuntimeException('PGOS project has no owner to attribute the publication; no articles were added.');
        }

        foreach (self::ARTICLES as [$key, $title, $category, $audience, $summary]) {
            $path = database_path('seeders/content/pms-qa-learning/'.$key.'.md');
            if (! is_file($path) || trim((string) file_get_contents($path)) === '') {
                throw new RuntimeException("Missing article content: {$path}; no articles were added.");
            }
        }

        DB::transaction(function () use ($project): void {
            if (PmWikiSpace::onlyTrashed()->where('key', 'PGOS')->exists()) {
                throw new RuntimeException('The PGOS wiki space is archived. Restore or rename it before publishing training articles.');
            }
            $space = PmWikiSpace::firstOrCreate(['key' => 'PGOS'], [
                'name' => 'PGOS Knowledge Sharing',
                'description' => 'PGOS product understanding, QA practice and PMS team workflows.',
                'visibility' => 'workspace',
                'owner_id' => $project->owner_id,
            ]);
            if ($space->visibility !== 'workspace') {
                throw new RuntimeException('The PGOS wiki space is restricted. Review its membership before publishing training articles.');
            }

            foreach (self::ARTICLES as [$key, $title, $category, $audience, $summary]) {
                $systemKey = 'qa-learning-'.$key;
                $existing = PmWikiPage::withTrashed()->where('system_key', $systemKey)->first();
                if ($existing) {
                    if ((int) $existing->project_id !== (int) $project->id) {
                        throw new RuntimeException("Existing article {$systemKey} belongs to another project; no project association was changed.");
                    }
                    if ($existing->access_scope !== 'workspace') $existing->update(['access_scope' => 'workspace']);
                    continue;
                }
                $content = trim(file_get_contents(database_path('seeders/content/pms-qa-learning/'.$key.'.md')));
                $slug = Str::slug($title);
                for ($suffix = 2; PmWikiPage::withTrashed()->where('space_id', $space->id)->where('slug', $slug)->exists(); $suffix++) {
                    $slug = mb_substr(Str::slug($title), 0, 165).'-'.$suffix;
                }
                $page = PmWikiPage::create([
                    'space_id' => $space->id, 'project_id' => $project->id,
                    'access_scope' => 'workspace', 'system_key' => $systemKey,
                    'slug' => $slug, 'title' => $title, 'category' => $category, 'audience' => $audience,
                    'summary' => $summary, 'content' => $content, 'tags' => ['PGOS', 'QA training'],
                    'status' => 'published', 'author_id' => $project->owner_id,
                    'last_editor_id' => $project->owner_id, 'lock_version' => 1, 'revision_number' => 1,
                    'published_at' => now(),
                ]);
                DB::table('pm_wiki_revisions')->insert([
                    'page_id' => $page->id, 'version' => 1, 'title' => $title, 'project_id' => $project->id,
                    'category' => $category, 'audience' => $audience, 'summary' => $summary,
                    'content' => $content, 'tags' => json_encode($page->tags), 'status' => 'published',
                    'status_code' => $page->status_code, 'editor_id' => $project->owner_id, 'created_at' => now(),
                ]);
            }
        });
    }
}
