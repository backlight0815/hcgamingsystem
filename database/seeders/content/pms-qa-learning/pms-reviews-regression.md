## Overview

Quality starts before a test is run. A clear requirement lets a Business Analyst (BA), developer and Quality Assurance (QA) Engineer share the same expected outcome. When the requirement is vague, QA may test the wrong rule or report a disputed defect. Requirement review clarifies the decision; retesting checks a specific fix; regression testing checks that related behavior still works.

## Who Should Read This

QA Engineers, BAs and Project Managers (PMs) reviewing PGOS requirements or preparing release validation.

## Learning Objectives

- Recognise measurable acceptance criteria and ask useful clarification questions.
- Explain when a BA change enters PM review.
- Distinguish retesting from regression testing.
- Record evidence, suggestions and residual testing risk.

## Key Concepts Explained

A **requirement** describes a needed business outcome. **Acceptance criteria** state conditions by which the team can tell whether that outcome is met. A testable criterion identifies a starting condition, action and observable result. “Users can create a goal” is useful direction, but it leaves deadline policy, required fields, save confirmation and persistence undefined. QA should ask questions without silently inventing rules.

A **requirement review** is a discussion of scope, ambiguities, examples and exceptions before implementation or testing. The BA owns clarification of business intent. The PM coordinates scope and approval. In PMS, some BA updates to project information and REQ/ENH tickets are routed as pending PM change reviews. They should not be treated as immediately approved; an active PM reviewer cannot approve their own request. Read the review history when acceptance criteria change during a cycle.

**Retesting** reruns the exact failure after a fix, using a known build and environment. **Regression testing** checks related workflows that might have been affected even if they did not fail before. Both matter: a fixed deadline field could save correctly while accidentally changing reminder behavior.

## Step-by-Step Instructions

1. Read the REQ or ENH, project context and any linked QAT tickets. Identify the user, intended outcome and business reason.
2. Mark every unclear term: “soon,” “valid,” “on time,” or “progress” needs a rule or example. Ask the BA specific questions and record the answer in the ticket or approved requirement.
3. Convert clarified rules into positive, negative and boundary test cases. Include data persistence and meaningful user feedback.
4. If the BA changes project or REQ/ENH details, check whether PMS has created a pending PM review. Wait for the actual approval outcome before treating the revision as baseline scope.
5. When a defect is fixed, reproduce the original issue in the corrected build and record the retest result, evidence, environment and any changed conditions.
6. Select a small regression set around the changed behavior. For a goal deadline change, inspect overdue indicators, reminders, goal health, editing and reopening.
7. Report defects separately from improvements. Explain any untested case, missing device permission or unavailable environment as a risk, not as a pass.

## Real-World PGOS Example

Consider the statement: **“Users should be able to create a goal with a deadline.”** QA cannot yet know whether a deadline is mandatory, whether a past date is allowed, whether it can be changed, how overdue goals appear, or whether changing it reschedules reminders. QA brings these questions to the BA and PM. A clearer criterion might say: “Given a new goal with a title and a future deadline, when the user saves it, the goal list and detail view display that deadline after the app is reopened.” A separate criterion should define missing or past deadlines if those cases matter. These are **illustrative proposed criteria**, not claims about current PGOS rules.

Once the rule is agreed, QA tests a valid future date, an omitted date if allowed, a past date according to policy, and an edited deadline. If saving fails, QA raises a QAT defect. When the developer supplies a fix, QA retests the exact case and then checks reminder timing and overdue display as regression coverage. The result is a better business conversation and fewer disputes about whether a finding is valid.

## Common Mistakes

- Replacing an unclear requirement with QA's own unrecorded assumption.
- Calling a proposed BA change approved before its PM review is complete.
- Recording “pass” without a build, environment, input and observed result.
- Retesting only the happy path when the original bug required special preconditions.
- Treating a usability improvement as proof that the agreed requirement failed.

## Best Practices

Ask concise questions with examples. Keep the accepted answer near the ticket so future testers can find it. Make acceptance criteria observable rather than describing internal code. When recording evidence, include just enough screenshots or notes to reproduce the result without exposing personal data. Tell the PM how an untested case could affect users.

## QA Checklist and Key Takeaways

- [ ] User outcome and acceptance criteria are understandable.
- [ ] Required, optional, past-date and edit behavior clarified.
- [ ] BA changes have the required review outcome.
- [ ] Original failure retested on a known build.
- [ ] Related journeys covered by regression tests.
- [ ] Evidence, improvement ideas and remaining risk separated.

## Related Knowledge-Sharing Articles

Read **How to Test PGOS**, **How to Create Professional Bug Reports and QAT Tickets**, and **Understanding Development Cycles, Release Management & QA Testing**.
