## Overview

A project coordinates people and outcomes; a ticket assigns a particular piece of work. Being a member of the Personal Goal Operating System (PGOS) project lets a person see authorised PGOS material, but it does not make that person responsible for every ticket. Clear ownership prevents tasks from waiting unnoticed between Business Analysis, development and Quality Assurance (QA).

## Who Should Read This

Project Managers (PMs), Business Analysts (BAs) and QA Engineers organising or joining project work.

## Learning Objectives

- Find project objectives, members, assignments and overdue work.
- Separate project access from ticket responsibility.
- Explain the usual BA, PM, developer and QA contributions.
- Change assignments within the actual PMS permission rules.

## Key Concepts Explained

The **project description** explains purpose and scope. Objectives and dates help the team assess progress, but a blank or outdated description should prompt clarification. **Membership** grants project-level access based on role. **Ticket assignments** identify specific workers: PMS has developer and QA assignee fields, a project manager association and contributors where configured. One person may belong to several projects, so always check the project key before editing a ticket.

The **PM** coordinates scope, ownership, priorities and delivery risks. The **BA** clarifies business needs and acceptance criteria. The **developer** investigates or implements the agreed behavior and records meaningful updates. The **QA Engineer** plans tests, records findings, verifies fixes and communicates residual risks. These responsibilities cooperate; they do not imply that one role can approve every action.

System administrators have broad project access. Project Managers can manage their projects. Other roles can view assigned projects and perform actions allowed by their role and ticket state. PMS may preselect or lock the project when a ticket is started from a project context or only one eligible project is available; verify the selected project before saving. A person in multiple projects should use project filters and confirm the project on every new ticket.

## Step-by-Step Instructions

1. If authorised to create a project, enter a unique key, clear name, description, objectives and relevant dates. Ordinary team members should ask an administrator or responsible manager when the Create action is unavailable.
2. Open the project and read its description, membership list, cycles, releases and open work. Identify the owner and the PM responsible for decisions.
3. For each ticket, check its classification, project, current developer, QA owner, manager and contributors. A project member without a ticket assignment may still comment or review if their permissions allow it, but should not assume ownership.
4. PMs allocate work using eligible active project members. PMS validates developer and QA roles for assignment; it also restricts some assignment changes to managers. If the chosen employee is unavailable, update membership or role first through the authorised process.
5. When responsibility changes, add a concise reason or handover comment, confirm the new assignee, and check that the next person knows the expected action.
6. Filter the project or ticket dashboard for overdue work, blocked items and unresolved dependencies. Contact the responsible owner with the specific ticket and required next step.
7. Near completion, compare accepted requirements, QA verification, open defects and release status. The PM should coordinate a decision on outstanding work rather than treating a dashboard count as approval.

## Real-World PGOS Example

Suppose PGOS needs a clearer goal-reminder experience. The BA documents what the user should see, when reminders should appear and the acceptance criteria in a REQ or ENH. The PM confirms the project's scope, identifies a developer and QA owner, and assigns or coordinates the tickets. The developer implements the agreed behavior and records the build or change context. QA checks timing, permission behavior, missed reminders and usability, then reports any defect as a QAT ticket. The PM monitors unresolved work and decides what can be included in the planned release with the appropriate authorised roles.

If QA is a PGOS project member but not assigned to the reminder QAT item, they can still understand the project context. The named QA assignee is responsible for recording the specific verification result. This distinction keeps accountability clear.

Business changes have an additional safeguard: PMS can route BA changes to project information or REQ/ENH ticket changes into a pending PM review. Such a request is not an immediate final change. The reviewer must be an active PM and cannot approve their own request. Team members should check the review outcome before relying on proposed wording as approved scope.

## Common Mistakes

- Equating project membership with ownership of every open ticket.
- Assigning a developer or QA person who is not eligible for that project and role.
- Assuming a proposed BA change is already approved.
- Moving work to another project without checking dependencies, release and assignees.
- Leaving overdue or blocked tickets without a named next action.

## Best Practices

Keep project descriptions and member roles current. Use ticket comments for handovers and decisions. Check both assignment and status when asking for a progress update. When a person works across several projects, use the project key in discussions and avoid relying on similar ticket titles alone.

## QA Checklist and Key Takeaways

- [ ] Project purpose, key and owner confirmed.
- [ ] BA, PM, developer and QA responsibilities identified.
- [ ] Each ticket has a suitable next owner or explicit blocker.
- [ ] Assignment changes follow role and approval rules.
- [ ] Overdue and blocked work is reviewed before declaring completion.

## Related Knowledge-Sharing Articles

Read **Getting Started with PMS**, **Understanding Project Tickets**, and **Understanding Development Cycles, Release Management & QA Testing**.
