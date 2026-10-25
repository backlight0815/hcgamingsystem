# Project Management 2.3.0 development report

**Implementation date:** 18 October 2026 (Asia/Singapore)  
**Previous version:** 2.2.0  
**Current version:** 2.3.0  
**Module:** Project Management

## Implementation summary

1. **Cycle directory:** Replaced the plain list with stored-record KPIs, card and table views, search, project/status/owner/date filters, sorting, pagination, phase completion, ticket counts, dates, owner, and compact actions. Creation opens a modal.
2. **Cycle detail and timeline:** Added Overview, Timeline, Tickets, Milestones, and Activity tabs. The existing date-axis Gantt renderer retains day/week/month zoom, planned and actual dates, progress, today, and milestones. Schedule elapsed, work completed, and quality are separate. Empty work is labelled “No work data.” Replanning preserves phase execution fields and history. Closed and cancelled cycles reject edits.
3. **Project detail:** Added summary header, real ticket/release/cycle metrics, seven content tabs, compact properties, contextual ticket creation and actions, scoped team controls, attachment gallery, and activity. Existing ticket, board, release, cycle, wiki, and team routes remain in use.
4. **Description formatting:** Existing Markdown is rendered with safe link and HTML rules. Rich HTML submitted to the server is converted into canonical Markdown for headings, paragraphs, emphasis, lists, links, code, blockquotes, and simple tables. The edit mode has formatting controls, server rendered preview, Save/Cancel, and HTML clipboard conversion. Stored descriptions required no migration.
5. **Project actions:** Four permitted ticket types appear in the Create Ticket menu. Board and other destinations are accessible from tabs or compact menus. Archive and delete retain existing manager checks and confirmation.
6. **Permissions:** PM and BA can update scoped projects, ticket details across REQ/ENH/PRD/QAT, release details, and development cycle details. Ticket project moves require access to both projects and a reason; locked releases cannot be bypassed. Status transitions, review decisions, release approvals, deletes, and role assignment remain separately controlled. EHN remains accepted and is displayed as ENH.
7. **Evidence:** Project and ticket pages use one gallery. Authenticated image thumbnails are generated and cached as private WebP derivatives. PDF and video use distinct cards; PDF/image/video preview and file download use authorized routes. The dialog supports image navigation and zoom. Project uploads accept a ticket reference; the original attachment remains associated with its ticket. Drag and drop, progress, validation feedback, metadata, copy link, and authorized deletion are present.
8. **Design and performance:** Added responsive workspace styles for the existing light/dark theme system, scrollable tabs, compact menus, lazy thumbnails, paginated attachments, scoped metrics, and eager loading. No existing record or route was removed.
9. **Testing:** Ran the full framework suite and focused redesign suite. See the exact QA matrix below.
10. **System Log:** Added version 2.3.0 entries for ENHANCEMENT, FIX, and NEW in `config/pms.php`.

## Modified files

| File or group | Purpose |
|---|---|
| `app/Http/Controllers/Pms/ProjectController.php` | Scoped project edit, activity and metrics, Markdown preview, project attachment upload. |
| `app/Http/Controllers/Pms/CycleController.php` | Directory queries, scoped cycle edits, phase preservation and locks. |
| `app/Http/Controllers/Pms/TicketController.php` | BA/PM ticket field updates and scoped project moves; defect and estimate fields. |
| `app/Http/Controllers/Pms/ReleaseController.php` | Scoped release detail and scope editing; existing approval transitions retained. |
| `app/Http/Controllers/Pms/TicketAttachmentController.php` | Authorized thumbnail and inline preview responses; derivative cleanup. |
| `app/Support/PmsAccess.php`, `app/Support/PmsProjectDescription.php` | Edit abilities and safe Markdown normalization/rendering. |
| `app/Services/PmsAttachmentThumbnail.php`, `app/Services/PmsCycleService.php` | Private cached thumbnails; distinct schedule/work health calculations. |
| `resources/views/pms/{cycles,cycle,project,ticket,release,attachment-preview,layout}.blade.php` | Redesigned pages and shared gallery integration. |
| `resources/views/pms/partials/{ticket-edit,project-activity,cycle-timeline}.blade.php` | Edit form, audit details, and truthful timeline metrics. |
| `public/backend/assets/js/{pms-cycle-directory,pms-detail-tabs,pms-project-editor,pms-attachment-gallery,pms-delivery}.js` | Modal/tabs/editor/gallery behavior and single upload handler. |
| `public/backend/assets/css/{pms-cycle-dashboard,pms-workspace,pms-workspace-theme}.css` | Responsive layout and theme styles. |
| `routes/web.php`, `config/pms.php` | Private preview/upload routes and 2.3.0 System Log. |
| `tests/Feature/PmsWorkspaceRedesignTest.php` | New authorization, Markdown, cycle, gallery, and regression coverage. |

## Database and compatibility

No migration, table recreation, or destructive database command was needed. Descriptions remain in the existing project column as Markdown. Attachments remain in `pm_ticket_attachments` and private local storage; image derivatives are generated on first request under private `pms/thumbnails/`. Existing records, filenames, ownership, timestamps, and ticket associations are unchanged. Release and cycle history continue to use existing audit tables.

## Effective permission matrix

| Action | Super Admin / Project Administrator | Project Manager | Business Analyst | QA / Developer |
|---|---|---|---|---|
| View scoped records | Yes | Yes | Yes | Existing scoped policy |
| Edit project, cycle, release details | Yes | Yes | Yes | No |
| Edit REQ, ENH/EHN, PRD, QAT ordinary details | Yes | Yes | Yes | Existing limited assigned-work policy |
| Move ticket to another project | Yes, with target access and reason | Yes, with target access and reason | Yes, with target access and reason | No |
| Submit REQ/ENH review | Yes | Yes | Yes | No |
| Decide another author's review | Yes, according to review policy | Yes, according to review policy | No | No |
| Advance release / approve deployment | Existing workflow policy | Stage changes only | No | No |
| Delete project, ticket, release, cycle | Yes | Yes, within existing scope | No | No |
| Add or remove project members | Yes | Existing restricted role set | No | No |

Ordinary edits do not write status transition or approval fields directly. A locked release rejects scope moves. Closed/cancelled cycles reject schedule and phase edits. Archived projects reject edits and uploads.

## UI changes

| Area | Before | After |
|---|---|---|
| Development Cycles | Plain list and collapsed create form | KPI summary, search/filter/sort, cards/table, modal create, scoped actions. |
| Cycle Detail | Dense single page | Tabbed overview, Gantt timeline, ticket scope, milestones, activity, and edit controls. |
| Project Detail | Long plain description and stacked links | Formatted read mode, edit preview, metrics, tabs, contextual ticket menu and compact properties. |
| Evidence | Generic cards and original images as thumbnails | Private generated thumbnails, file-specific cards, metadata, lightbox, upload progress and validation. |

## QA results

**Full regression run:** `php artisan test --compact --log-junit storage/app/pms-2.3.0-junit.xml` — **229 passed, 1 skipped, 5,997 assertions**. Two focused tests and minor project-upload/cycle-display refinements followed that run; their affected paths passed the focused suite.  
**Final focused run:** `php artisan test --filter=PmsWorkspaceRedesignTest --compact` — **8 passed, 115 assertions**.  
**Static checks:** Blade view cache, changed PHP lint, JavaScript syntax checks, and `git diff --check` passed.  
**Browser QA:** No browser surface was available in this workspace, so click behavior and viewport rendering were not marked as passed.

| ID | Status | Evidence or limit |
|---|---|---|
| TC-001 | PASS | Cycle directory feature request. |
| TC-002 | PASS | Stored cycle names and counts asserted. |
| TC-003 | PASS | Search and table/card filters asserted. |
| TC-004 | PASS | Authorized cycle creation in framework suite. |
| TC-005 | PASS | Cycle detail request asserted. |
| TC-006 | PASS | Existing phase dates asserted in detail/timeline suite. |
| TC-007 | PASS | Empty phase schedule detail asserted. |
| TC-008 | PASS | Existing cycle analytics and empty-work checks asserted. |
| TC-009 | NOT RUN | Mobile browser layout unavailable. |
| TC-010 | PASS | Markdown H2 output asserted. |
| TC-011 | PASS | Unordered list output asserted. |
| TC-012 | PASS | Ordered list output asserted. |
| TC-013 | PASS | Paragraph output asserted. |
| TC-014 | PASS | Rich HTML normalization asserted server-side. Browser clipboard interaction not run. |
| TC-015 | PASS | Existing Markdown rendering through project detail asserted. |
| TC-016 | PASS | Script and unsafe link neutralization asserted. |
| TC-017 | NOT RUN | Long-description mobile browser layout unavailable. |
| TC-018 | PASS | Project header content asserted. |
| TC-019 | NOT RUN | Menu click interaction requires a browser. |
| TC-020 | PASS | REQ project association in existing ticket creation suite. |
| TC-021 | PASS | ENH project association in existing review/creation suite. |
| TC-022 | PASS | QAT project association in delivery suite. |
| TC-023 | NOT RUN | Tab click interaction requires a browser. |
| TC-024 | PASS | Project view source inspection confirms stacked action group removed. |
| TC-025 | PASS | PM project update asserted. |
| TC-026 | PASS | BA project update asserted. |
| TC-027 | PASS | PM updates all four categories asserted. |
| TC-028 | PASS | BA updates all four categories asserted. |
| TC-029 | PASS | PM release update asserted. |
| TC-030 | PASS | BA release update asserted. |
| TC-031 | PASS | PM cycle update asserted. |
| TC-032 | PASS | BA cycle update asserted. |
| TC-033 | PASS | QA project update denied. |
| TC-034 | PASS | Developer release update denied. |
| TC-035 | PASS | BA ticket deletion denied. |
| TC-036 | PASS | PM deletion in existing management flow suite. |
| TC-037 | PASS | Out-of-scope project edit and private file access denied. |
| TC-038 | PASS | Project audit record and ticket/release/cycle history asserted. |
| TC-039 | PASS | Uploaded PNG served as private WebP thumbnail. |
| TC-040 | PASS | Uploaded JPEG served as private WebP thumbnail. |
| TC-041 | PASS | PDF card and file route asserted. |
| TC-042 | NOT RUN | Image lightbox click requires a browser. |
| TC-043 | PASS | Authorized PDF inline preview response asserted; browser viewer not checked. |
| TC-044 | PASS | Authorized original file download asserted. |
| TC-045 | PASS | Unauthorized thumbnail/file access denied. |
| TC-046 | PASS | Rejected executable evidence in existing suite. |
| TC-047 | PASS | Existing attachment record and original download path used without migration. |
| TC-048 | NOT RUN | Mobile gallery browser layout unavailable. |
| TC-049 | PASS | REQ creation regression suite. |
| TC-050 | PASS | Review submission regression suite. |
| TC-051 | PASS | Release management regression suite. |
| TC-052 | PASS | Cycle/release relationship regression suite. |
| TC-053 | PASS | Team management regression suite. |
| TC-054 | PASS | TradingHub tests in full suite. |
| TC-055 | PASS | Dealership and shared backoffice tests in full suite. |
| TC-056 | PASS | Super Admin access tests in full suite. |

## Outstanding limits

- Desktop, tablet, and phone rendering, tab/menu clicks, clipboard paste behavior, and lightbox interactions still need browser QA on a workstation with a browser surface. Server routes and JavaScript syntax passed.
- PDFs use a file-specific card and the browser's inline PDF viewer; no first-page PDF image is generated. Viewer availability depends on the user's browser.
- The project gallery stores files against an existing project ticket because this application's attachment schema is ticket-based. The ticket reference can be typed even if it is absent from the recent suggestions.
- Direct release attachments are not present in the existing schema, so the shared gallery is on project and ticket detail pages.

## System Log

`config/pms.php` records **2.3.0**, dated **2026-10-18**, for Project Management:

- **ENHANCEMENT:** cycle directory/detail, project UI, release editing, gallery.
- **FIX:** safe Markdown rendering, scoped PM/BA editing, private image/PDF previews.
- **NEW:** cycle view modes, contextual project actions/tabs, cached thumbnails, lightbox.
