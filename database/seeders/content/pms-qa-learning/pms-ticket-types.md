## Overview

PMS uses different records for different questions. A project says **where** work belongs. A Requirement (REQ) says **what outcome is needed**. An Enhancement (ENH) improves existing behavior. A Production Issue (PRD) records harm observed in the live environment. A Quality Assurance Ticket (QAT) records a testing defect or issue to investigate. Choosing the right type makes work easier to assign, review and report.

## Who Should Read This

QA Engineers, Business Analysts (BAs), Project Managers (PMs), and anyone creating or linking project tickets.

## Learning Objectives

- Choose a suitable classification for a common PGOS situation.
- Distinguish a project from a ticket and a requirement from a defect.
- Use relationships to trace why a fix matters.
- Recognize when role permissions limit ticket creation or changes.

## Key Concepts Explained

**Project:** The overall container for goals, people, tickets, cycles and releases. A PGOS project page can describe objectives and show related knowledge. It is not itself a bug report.

**REQ:** A needed behavior or business outcome. Example: “Allow a user to create a measurable goal with a deadline.” A useful REQ explains why it matters and what acceptance looks like.

**ENH:** A proposed improvement to existing behavior. Example: “Explain the goal-progress summary in simpler words.” Record the current experience and desired improvement rather than assuming the current behavior is broken.

**PRD:** A production issue affecting real live use. Example: “Existing users cannot open a critical goal feature in production.” Include environment, impact, timing and safe evidence. A confirmed live issue deserves prompt coordination.

**QAT:** A defect or test finding discovered in a QA environment or testing activity. Example: “Goal progress does not change after a milestone is completed.” State the expected rule, actual result and repeatable steps. The example is illustrative; it is not a confirmed PGOS defect.

PMS also uses Task (TSK) tickets for planned work. A task might cover implementation or follow-up that is not itself a business requirement or QA defect. Classification does not replace project selection: the same type may exist in several projects.

## Step-by-Step Decision Guide

1. Ask whether you are describing the whole body of work or one issue. Use the **project** for the overall context.
2. If the product needs a new capability or clearly stated behavior, use **REQ**.
3. If existing behavior works but could be more useful, use **ENH**.
4. If users are affected in the live environment, use **PRD** and record production impact.
5. If a test found behavior contrary to an agreed expectation, use **QAT**.
6. If the work is an implementation or coordination task, consider **TSK** where your role permits it.
7. Search for existing tickets before creating another. Link or comment when the issue is already tracked.

## Real-World PGOS Example

A team plans a new goal-planning experience. The BA writes a REQ covering measurable goals and acceptance criteria. The developer may have a TSK for a specific implementation step. During testing, QA finds that completing a milestone leaves the displayed progress unchanged and creates a QAT linked to the REQ. Later, users report that the same screen cannot be opened on the live service; the team records a PRD with production context. Separately, a proposal to explain progress more clearly is an ENH. Each record answers a different question while staying connected to the PGOS project.

PMS supports a ticket parent and child structure, related-ticket links, QAT dependency links and sub-issue links. A child or sub-issue can divide a larger piece of work. A related link shows context without claiming one ticket blocks another. A QAT dependency means a prerequisite must be resolved before certain retest or completion transitions. Use these links deliberately; false dependencies can delay valid work.

## Common Mistakes

- Calling a desired improvement a production incident without evidence of live harm.
- Filing the same defect in multiple classifications instead of linking existing records.
- Linking a QAT to an unrelated requirement only to fill a field.
- Assuming every project member can create every ticket type. Active QA roles can create QAT items within their authorised project; BA and PM roles can create several business ticket types. Administrators or profiles with broader creation rights have additional options. Permissions and assignment rules still apply.

## Best Practices

Use the project's correct key, describe the user impact, and link the nearest relevant requirement. Keep parent-child and dependency links specific. When uncertain whether a report is QAT or PRD, confirm the environment and discuss with the PM. A classification can guide triage, but it cannot make an unclear report useful.

## QA Checklist and Key Takeaways

- [ ] Correct PGOS project and environment selected.
- [ ] The ticket type matches the problem or proposed outcome.
- [ ] Existing tickets checked for duplicates.
- [ ] Useful parent, related or dependency links added only where justified.
- [ ] Role and assignment rules respected.

## Related Knowledge-Sharing Articles

Continue with **How to Create Professional Bug Reports and QAT Tickets**, **Understanding Ticket Status, Priorities, Blockers & Resolutions**, and **How to Manage Projects, Assignments & Team Responsibilities**.
