## Overview

Quality Assurance (QA) checks whether PGOS supports a complete, reliable goal-planning journey. A button that responds is only one part of the result. QA also asks whether saved information stays consistent, progress means what users think it means, reminders are understandable, and a user can recover when a plan slips. The scenarios below are **test designs, not reports of tests performed or defects confirmed**.

## Who Should Read This

QA Engineers preparing functional, exploratory, usability or regression testing of PGOS. Business Analysts can use the questions to clarify acceptance criteria.

## Learning Objectives

- Derive test cases from a requirement and its business purpose.
- Cover successful, invalid, boundary and interrupted journeys.
- Distinguish a defect from confusing design and an optional improvement.
- Record enough context and evidence for another person to repeat a result.

## Key Concepts Explained

**Positive testing** uses valid data and checks the intended result. **Negative testing** uses missing or invalid data and checks a safe, clear response. **Boundary testing** checks limits such as an empty title or a deadline close to the current date. **Exploratory testing** follows plausible user paths beyond a fixed script. **Usability testing** asks whether a person can understand the result. **Regression testing** repeats important checks after a change. **Data consistency testing** compares related screens and saved information after reopening. Start by reading the relevant requirement and asking the BA about unclear rules.

## Step-by-Step Testing Method

1. Confirm the build, device, app permissions, test account or local data state, and applicable requirement.
2. Create a realistic goal with milestones and actions; record the starting values.
3. Run a successful path, then invalid inputs and boundary cases.
4. Check related summaries and reopen the app to confirm persistence.
5. Note exact steps, observed result, expected result, and evidence. Label exploratory observations clearly.
6. Discuss uncertain expected behavior before filing a definite defect; suggest improvements separately.

## Real-World PGOS Scenarios

**A — Create a goal.** Objective: confirm a new goal is usable. Preconditions: a fresh or known local data state. Steps: enter a valid title and details, save, return to the goal list, reopen it. Expected: the same goal and details appear. Look for duplicates or lost fields. Impact: users cannot trust planning. Opportunity: clearer confirmation after saving.

**B — Missing information.** Objective: test required-field handling. Preconditions: goal form open. Steps: omit a field marked required, submit, then correct it. Expected: a clear prompt and no incomplete duplicate. Look for silent saves or erased input. Impact: poor data quality. Opportunity: explain the requirement beside the field.

**C — Milestone update.** Objective: check linked progress. Preconditions: a goal with incomplete milestones. Steps: record initial progress, complete one milestone, inspect the summary. Expected: the value follows the documented calculation rule. Look for stale figures or inconsistent screens. Impact: misleading progress. Opportunity: explain the calculation in the interface.

**D — Complete an action.** Objective: inspect related state. Preconditions: an open action under a goal. Steps: complete it, inspect action, milestone and goal views, reopen. Expected: completion persists and related indicators follow their rules. Look for double counting. Impact: false confidence. Opportunity: show which milestone the action supports.

**E — Past deadline.** Objective: assess overdue handling. Preconditions: a goal whose deadline can be set to a past date under the applicable requirement. Steps: set or simulate that deadline, reopen, inspect status. Expected: behavior matches the clarified policy. Look for contradictions between date and health label. Impact: users miss needed changes. Opportunity: offer a clear review prompt.

**F — Reminder behavior.** Objective: check planned prompts. Preconditions: notification permission and reminder settings documented. Steps: schedule an eligible reminder, wait or simulate the trigger, open the prompt. Expected: timing and goal context match the rule. Look for duplicate or irrelevant notifications. Impact: missed work or alert fatigue. Opportunity: clearer scheduling feedback.

**G — Edit and reopen.** Objective: test persistence. Preconditions: an existing goal. Steps: change its details, close and reopen the app, inspect list and detail views. Expected: the edited values remain consistent. Look for old values after restart. Impact: users may act on an obsolete plan. Opportunity: visible last-updated information.

**H — Missed work.** Objective: assess recovery. Preconditions: an overdue planned action. Steps: leave it incomplete, inspect the goal, reschedule or revise where available. Expected: the app represents the missed work honestly and saves the revised plan. Look for automatic completion or confusing blame language. Impact: users abandon planning. Opportunity: a supportive next-step hint.

**I — Priority and health.** Objective: test understanding. Preconditions: goals with different priorities and progress. Steps: compare their summaries and ask a new user what needs attention. Expected: labels and signals support the documented interpretation. Look for unexplained color-only meaning. Impact: poor decisions. Opportunity: plain-language reasons for at-risk status.

**J — Offline use.** Objective: check the implemented local-first scope. Preconditions: known data saved on the device. Steps: disconnect, create or edit a goal, reopen offline, reconnect. Expected: local data persists. **Do not assume cloud synchronization exists**; check only a documented sync feature if present. Look for lost local edits. Impact: unreliable everyday use. Opportunity: clearer offline-state messaging.

## Common Mistakes

A **functional defect** is a valid milestone change that fails to save. A **usability issue** is a saved change whose progress display is difficult to understand. An **enhancement recommendation** is an optional explanation of why a goal is at risk. Do not report all three as the same failure. Avoid claiming a scenario passed when it was only proposed.

## Best Practices

Keep test data small enough to calculate expected outcomes manually. Vary dates, priorities and completion order. Preserve screenshots or recordings with sensitive personal content removed. Link confirmed defects to the relevant requirement and describe their user impact.

## QA Checklist and Key Takeaways

- [ ] Requirement and expected rule confirmed.
- [ ] Valid, invalid, boundary and interrupted paths considered.
- [ ] Related screens and reopened data checked.
- [ ] Actual result separated from expectation and improvement ideas.
- [ ] Evidence and build context recorded.

## Related Knowledge-Sharing Articles

Read **Understanding PGOS**, **How to Create Professional Bug Reports and QAT Tickets**, and **How to Perform Requirement Reviews, Retesting & Regression Testing**.
