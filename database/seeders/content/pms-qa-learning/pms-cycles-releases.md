## Overview

A development cycle groups work the team plans to address. A release groups changes intended for delivery to users. The PGOS project is the continuing product context; a ticket is one piece of work; a cycle helps organise effort; a release tracks what is expected to go out. Quality Assurance (QA) needs to know which work is included, what has been verified and what remains risky before a release decision.

## Who Should Read This

QA Engineers, Project Managers (PMs), Business Analysts (BAs), Developers and release coordinators.

## Learning Objectives

- Distinguish a project, ticket, cycle and release.
- Prepare tests from the actual work selected for a release.
- Retest fixes and run regression checks before communicating readiness.
- Explain why a passed QA result does not equal production completion.

## Key Concepts Explained

A **cycle** gives the team a planning context for related tickets. PMS has project cycles and allows eligible tickets to be associated with one. A **release** carries a version or release key and a planned delivery context; tickets may be linked to it. PMS checks that a selected cycle or release belongs to the same project. It can lock a release's scope once it reaches later stages, so moving a ticket may require an authorised change and a reason. No fixed cycle length is assumed: use the dates and process defined by the real PGOS project.

**Ready for QAT** on a QAT ticket means the reported fix is available for an assigned QA Engineer to validate. It is not proof of success. **QAT Done** follows a passed QA verification, while **Production Done** is a later delivery milestone. For other ticket types, PMS has its own delivery stages such as Planned, Open, In Progress, Ready for Internal Testing, Internal Testing, QAT Testing and Completed. Read the actual ticket's workflow before applying a QAT-specific rule.

**Retesting** repeats the original failure under the corrected build. **Regression testing** checks related behavior that could have been affected by the change. A release may contain unresolved QAT work; the PM and authorised release roles need a clear impact assessment rather than an unsupported “all good” statement. A release decision belongs to the team's configured process; this lesson does not invent an approval threshold.

## Step-by-Step Instructions

1. Open the PGOS project and inspect its planned cycle and release. Confirm the release key, version, current status and ticket list.
2. Read each included REQ and ENH, especially acceptance criteria and recent approved changes. Identify QAT fixes and unresolved defects that affect the same journeys.
3. Build a test plan around complete user tasks: create a goal, update milestones and actions, inspect progress, handle reminders and reopen saved data. Add device or permission variations relevant to the changes.
4. Confirm the test build and environment with the developer or release coordinator. If a QAT ticket is Ready for QAT, have the assigned QA owner begin verification under the correct state.
5. Execute the planned tests and record actual results, evidence and build information. Create new QAT tickets for reproducible failures, linked to relevant requirements.
6. Retest each resolved issue using the original steps. Record Passed or Failed using the QAT verification flow. A failed result returns current QAT work to Reopen for investigation.
7. Run regression checks around nearby behavior. A reminder change, for example, may affect scheduling after a deadline edit, notification permission behavior and the displayed goal state.
8. Summarise the release scope, tested journeys, passed checks, untested areas, open defects, blockers and user impact. Send the summary to the PM and release decision makers before scope is declared ready.

## Real-World PGOS Example

Imagine a PGOS release containing a goal-management improvement, a reminder enhancement and several QAT fixes. QA reads the linked requirement for each item, then chooses sample goals with short and long deadlines. They verify the improved goal journey, reminder timing under the documented rules, and the original QAT reproduction steps. They reopen the app to check saved changes and test related reminder settings as regression coverage. One unresolved low-impact display issue might be documented with a workaround; an unresolved failure to save goals needs a much stronger risk statement. QA reports both facts and consequences, while the authorised team decides whether to delay, reduce scope or proceed.

## Common Mistakes

- Testing only the new feature while overlooking changed existing journeys.
- Treating Ready for QAT as a passed result or QAT Done as proof of live deployment.
- Including a ticket in a release without checking its project and current release scope.
- Reporting a release as ready without naming untested areas and open defects.
- Assuming every project follows the same cycle length or release approval process.

## Best Practices

Keep a short trace from release item to requirement, test evidence and QAT outcome. Use representative real-world goal data. Discuss blockers early, especially unavailable builds or environments. Separate “tested and passed” from “not tested” and “accepted risk” in the release summary.

## QA Checklist and Key Takeaways

- [ ] Release, cycle, project and included ticket set confirmed.
- [ ] Acceptance criteria and approved changes reviewed.
- [ ] Original QAT issues retested in the correct build.
- [ ] Related regression journeys checked.
- [ ] Remaining defects, blockers and gaps described with user impact.

## Related Knowledge-Sharing Articles

Read **Understanding Ticket Status, Priorities, Blockers & Resolutions**, **How to Perform Requirement Reviews, Retesting & Regression Testing**, and **How to Manage Projects, Assignments & Team Responsibilities**.
