## Overview

A strong Quality Assurance Ticket (QAT) lets another person understand and repeat a problem. It separates evidence from interpretation and explains why the user is affected. QA's job is not to prove that a developer made a mistake; it is to make the observed behavior clear enough for the team to investigate and correct or explain it.

## Who Should Read This

QA Engineers who create, update or retest PGOS tickets. Developers and Business Analysts can use the structure when responding to findings.

## Learning Objectives

- Confirm an issue and its expected behavior before reporting it.
- Write a concise title and reproducible steps.
- Choose project, cycle, release and priority responsibly.
- Provide evidence and clear retesting results.

## Key Concepts Explained

**Precondition** is the starting state, including relevant data, environment and build. **Steps to reproduce** are actions another tester can repeat. **Actual result** is what was observed. **Expected result** comes from an agreed requirement or clarified business rule. **Impact** explains why the difference matters. An attachment supports the report but should not replace a written explanation.

PMS QAT records support fields for reproduction steps, expected and actual results, environment, defect type, attachments, developer and QA assignments, and optional cycle and release associations. The chosen release or cycle must belong to the selected project; a finished or locked release may not be mutable. A PM or other authorised manager controls certain assignments. If an assignment field is unavailable to you, request the change through the responsible manager rather than selecting an unrelated person.

## Step-by-Step Instructions

1. Read the relevant REQ or ENH and its acceptance criteria. Ask the BA to clarify an uncertain rule.
2. Reproduce the behavior at least once with known starting data. Record the build, environment, device and any conditions such as notification permission.
3. Search PMS for a similar QAT or PRD. Add evidence to an existing ticket if it is the same issue.
4. Select the correct project. Choose a cycle or release only when the finding belongs to that planned work and the option is available.
5. Write a title with the affected feature and observable failure. Use “Goal progress remains unchanged after milestone completion,” not “Goal is not working.”
6. Enter a short description of the user impact, then numbered steps with precise inputs and navigation.
7. Write the actual result without guessing the cause. Write the expected result using the agreed rule; mention a pending clarification if there is no confirmed rule.
8. Choose priority based on reach, severity and urgency. PMS labels are Critical, High, Normal and Low; do not assign Critical merely because the defect is easy to notice.
9. Attach a screenshot or recording that shows the relevant before-and-after states. Avoid exposing another person's private data.
10. Link the relevant requirement or related ticket where appropriate. Ask a manager for the right developer or QA assignment when needed.
11. After a fix, use the QAT verification flow to record the build, environment, result and evidence. If it still fails, include new steps and observations when reopening.

## Real-World PGOS Example

**Illustrative example — not a confirmed defect:**

**Title:** PGOS — Goal Progress Does Not Update After Completing a Milestone

**Precondition:** The user has an existing goal containing an incomplete milestone. The applicable progress-calculation rule has been confirmed with the requirement owner.

**Steps to reproduce:**

1. Open the existing goal.
2. Navigate to its milestones.
3. Complete an incomplete milestone.
4. Return to the goal summary.
5. Inspect the displayed progress.

**Actual result:** The displayed goal progress remains unchanged after the milestone completion. **Expected result:** The summary should reflect the completed milestone according to the product's documented progress-calculation rule. **Business impact:** Users may lose confidence in progress information and make poor planning decisions. **Evidence:** A screenshot or recording showing the milestone before and after completion, plus the goal summary. **Suggested priority:** Determine from actual impact and team policy; no fixed priority is assumed here.

The weak report “The goal is not working” omits the feature, action and symptom. The stronger title tells the developer which journey to inspect even before opening the ticket.

## Common Mistakes

- Reporting a vague symptom without preconditions or a repeatable sequence.
- Calling an assumption the expected result when the requirement is silent.
- Uploading evidence that cannot be opened or that contains private user information.
- Confusing a usability suggestion with a reproducible functional failure.
- Marking a QAT item complete without recording a QA verification result.

## Best Practices

Keep one distinct problem per report. Include the smallest data set that reproduces it. Compare behavior after a fresh reopen when persistence is relevant. Update the ticket with new facts; leave earlier evidence available so the history remains understandable. Use respectful, neutral language even when the impact is serious.

## QA Checklist and Key Takeaways

- [ ] Existing tickets searched and project confirmed.
- [ ] Title names feature and observable result.
- [ ] Preconditions, numbered steps, actual and expected results are distinct.
- [ ] Impact, build, environment and safe evidence included.
- [ ] Priority and assignment reflect current facts and permissions.
- [ ] Retesting result recorded through the QAT workflow.

## Related Knowledge-Sharing Articles

Read **How to Test PGOS**, **Understanding Ticket Status, Priorities, Blockers & Resolutions**, and **Professional Team Collaboration**.
