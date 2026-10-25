# PMS ticket validation, manager assignment, and account administration

## Deployment

1. Deploy the PHP, Blade, CSS, and JavaScript files together. The assets are already in `public/backend/assets`; no Node build is required.
2. Run `php artisan migrate --force`. Migration `2026_10_20_000007_add_pms_ticket_project_manager.php` adds a nullable `pm_work_items.project_manager_id` foreign key and index. Existing ticket rows remain valid with no manager. Any earlier pending PMS migrations run in their normal timestamp order.
3. Run `php artisan optimize:clear` and `php artisan view:cache` after the new views and assets are present.
4. Check the Account Management directory, a ticket create and edit screen, and the manager selector under a project with an active Project Manager member.

The migration was applied to the local development database. Production deployment was not performed.

## Behavior to verify in production

- Ticket manager eligibility uses active PMS account status and the `Project Manager` membership role on the ticket's project. A user's job position is shown for context but does not grant the role. Existing developer, QA, and PIC assignments are separate.
- A project manager is preselected only when the project's assigned manager is eligible. New tickets may leave this field empty, and the parent project manager is never changed by a ticket edit.
- Invalid workflow stage actions return HTTP 409 with `WORKFLOW_STAGE_RESTRICTION`; unauthorized actions remain HTTP 403. Field validation remains HTTP 422. API v1 keeps its existing response envelope and adds stage details for this conflict.
- Account status changes and manager assignments are recorded in existing PMS audit and ticket history tables. Suspended and inactive accounts cannot receive new assignments.

## Validation performed locally

- `php artisan test --filter=Pms --no-ansi`: 102 passed, 1,609 assertions after the final backend changes.
- `php artisan test --no-ansi`: 278 passed, 1 skipped, 6,769 assertions.
- `php artisan view:cache --no-ansi`, PHP syntax checks, and JavaScript syntax checks passed.
- Account directory rendered at 1440px, 768px, and 390px with no horizontal overflow, hidden action column, or browser script errors. Account detail rendered at desktop and mobile widths without horizontal overflow or script errors.
