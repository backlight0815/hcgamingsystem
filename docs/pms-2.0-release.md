# Project Management 2.0 — implementation and system log

Date: 2026-10-17. Version: 2.0.0.

## Changes

- Organization development cycles are independent of projects. The server scheduler generates consecutive 45-day cycles by default, catches up after missed runs, and records lifecycle decisions. Administrators can change the interval, anchor, and timezone, pause generation, edit dates with a reason, and archive completed cycles. Project Managers can assign projects and selected tickets without importing entire project backlogs.
- The release overview aggregates visible projects, tickets, QAT and delivery types, priorities, overdue work, milestones, and the current cycle. Project releases may use an assigned organization cycle or an existing project cycle.
- Ticket relationships support directional QAT dependencies and sub issues, bidirectional related tickets, unlinking, cycle prevention, and QAT retest gates. Ticket events are persisted and paginated, including field changes, comments, attachments, and relationship changes.
- New tickets use Low, Normal, High, and Critical; Normal is the default. Existing P0 records remain intact and display as Critical. The P3 database label is Normal.
- The knowledge base provides workspace and restricted spaces, nested pages, drafts and publishing, permission-aware search, favorites, page comments, links to tickets and projects, private attachments, optimistic edit locking, and restorable revisions. Markdown rendering strips embedded HTML and unsafe links.
- The wiki editor now includes underline markup and a live server-rendered preview using the same sanitized renderer as published pages.
- Development cycle pages now show organizational context, status, KPIs, stage timelines, tickets, project progress, analytics, activity, and administrative controls.
- Cycle date indicators follow the configured organization timezone, directory counts respect the viewer's project permissions, and the project assignment picker supports searching by key or name.
- Ticket activity times refresh in the browser, and relationships chosen during ticket creation are recorded in the activity of both tickets.

## Data migration

Run `php artisan migrate --force`. Migrations `2026_10_17_000001` through `000004` add the organization cycle tables and associations, ticket activity, wiki tables, and the Normal priority label. Existing project cycles, releases, tickets, and priority codes remain in place. No prior activity is invented for old tickets.

## Scheduler

The command `php artisan pms:generate-cycles` is scheduled hourly in Laravel's console kernel. Production must run Laravel's existing `schedule:run` cron every minute. The organization timezone and interval are configured in Development cycles → Cycle settings. The scheduler keeps transition and creation in one database transaction and relies on unique keys as a final duplicate guard.

## Routes and access

PMS routes under `/backoffice/project-management` include `/delivery-cycles`, `/releases`, `/tickets/{ticket}/links`, and `/wiki`. They inherit the existing authenticated PMS middleware. Space, page, ticket, project, cycle, and attachment access is checked on the server. Attachments use the private local disk and authenticated downloads.

## Operational notes

- Legacy `/cycles` and its historical project cycle links remain available.
- Organization cycle ticket carry forward moves the current association while retaining `original_global_cycle_id` and a cycle history event. It does not close, clone, or move every unresolved ticket.
- Release readiness uses legacy phase signoff for legacy cycles and stage completion for organization cycles.
- The ticket activity timeline starts at deployment. It does not fabricate events for older changes.
- Server-rendered screens use the existing PMS shell; no frontend build step is required for these additions.
- The local scheduler command generated one cycle on first run and zero on a second run. Production still needs the existing Laravel scheduler cron entry.

## Known limitations

- The wiki editor uses a Markdown toolbar and safe server rendering. Syntax highlighting and internal link previews are not implemented.
- Old ticket changes made before this release have no reconstructed activity events.
- Browser visual review was unavailable in this development session. Blade compilation and feature tests covered the server-rendered pages.
- The full application suite has one unrelated Real Market Insight failure: `RealMarketInsightTest` expects `CONNECTED` and receives `IDLE` at line 94.
