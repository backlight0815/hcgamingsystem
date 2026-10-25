# PMS QA learning center release

The ten articles live in `database/seeders/content/pms-qa-learning`. The first migration adds nullable classification and project fields to existing wiki tables. The follow-up migration adds `access_scope`, defaulting to `project` for existing and newly authored project pages. The ten seeded lessons use `workspace`, so active PMS users can read them without PGOS project membership. The seeder is intentionally **not** part of `DatabaseSeeder`; run it only after the PGOS project has been verified in the target environment.

## Deployment record — 21 October 2026

The production project was verified by key `PGOS`, name `PGOS`, and existing owner. The first cPanel release ran from `/home/hctradingh107303/pms_qa_learning_20261021/deploy.sh` at 09:41 Asia/Singapore. Its log reported the new migration ran, the seed command completed, view compilation succeeded, and `PMS_QA_LEARNING_DEPLOY_EXIT:0`. The temporary deployment cron entry was removed. phpMyAdmin showed 15 wiki pages afterward: five existing pages plus ten new `qa-learning-*` pages. The user then clarified that the lessons are general PMS knowledge, so a second release changed only their visibility to workspace while keeping the PGOS association. Local automated wiki tests passed (7 tests, 101 assertions).

The workspace visibility update ran from `/home/hctradingh107303/pms_qa_learning_general_20261021/deploy.sh` at 09:51 Asia/Singapore. Its log reported migration `2026_10_21_000002` and `PMS_QA_WORKSPACE_DEPLOY_EXIT:0`; its temporary cron entry was removed. The first live request then revealed that the local checkout's newer PMS layout referenced a partial absent from production. The production layout was restored from the release's automatic backup with only a versioned wiki stylesheet URL, and the status badge CSS was corrected. Final verification with a PMS user who has no PGOS project membership showed 15 visible pages, a readable new article, a working category filter, and a 403 on the PGOS project page. The local release package now contains the production-compatible layout and corrected stylesheet.

## Production preflight

1. Back up the production database and the wiki-related application files.
2. Verify that `pm_projects` contains exactly the intended active PGOS project, identified by key `PGOS` (or set `PMS_PGOS_PROJECT_KEY` to its verified key), with a name containing `PGOS` or `Personal Goal` and a real owner.
3. Check that the `PGOS` wiki space is active and has workspace visibility. Do not repurpose a restricted or unrelated space.
4. Confirm that the existing wiki pages and project membership records are intact.

## Publish

Copy the changed Laravel files, ten Markdown lessons, and public CSS file to the same relative locations under the live app and public root. Run in this order from the Laravel app directory:

```bash
/usr/local/bin/ea-php82 artisan migrate --force
/usr/local/bin/ea-php82 artisan db:seed --class='Database\Seeders\PmsQaLearningCenterSeeder' --force
/usr/local/bin/ea-php82 artisan optimize:clear
/usr/local/bin/ea-php82 artisan view:cache
```

The cPanel release package includes `deploy.sh` for these steps. It backs up overwritten files into its release directory. It checks the target paths, copies the migration first, then copies the application files and runs the seeder. A `deploy.done` marker makes a repeated cron invocation a no-op. Remove the temporary cron entry once the log reports `PMS_QA_LEARNING_DEPLOY_EXIT:0`.

## Verify

Check the live wiki as an assigned PGOS QA member and as an unrelated active PMS member. Both should see and open the ten new published lessons, use category filters and search, view related articles, and retain access to the existing technical pages. The unrelated member should still be denied access to the PGOS project page and any project-scoped wiki page. Review the ten `qa-learning-*` rows in `pm_wiki_pages`, their `project_id`, `access_scope = workspace`, and revisions. Re-running the seeder should leave the count at ten and preserve any employee edits.

If the seeder rejects the project key or space, inspect the target records and correct the configuration. Do not force the articles onto another project ID. A failed migration or seed should be investigated from the deployment log before retrying; the migration is additive and the seeder inserts its articles in one transaction.
