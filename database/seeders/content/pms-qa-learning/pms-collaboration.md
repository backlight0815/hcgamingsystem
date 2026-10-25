## Overview

PMS is a shared record of decisions and work. Comments explain a new observation or next action; ticket fields capture the current structured state; attachments provide evidence; history shows how the record changed. Knowledge Sharing turns repeated explanations into reusable guidance. Clear communication saves time and makes disagreements easier to resolve with facts.

## Who Should Read This

All PGOS project team members: QA Engineers, Developers, Business Analysts (BAs), Project Managers (PMs) and release coordinators.

## Learning Objectives

- Choose between updating a ticket field and adding a comment.
- Write actionable QA, developer, BA and PM updates.
- Attach relevant evidence and use history to understand decisions.
- Improve a knowledge article without duplicating discussion.

## Key Concepts Explained

Use a **field update** when the ticket's official state changes: assignment, priority, status, release, expected result or other structured details. Use a **comment** to explain why, share an observation, ask for clarification, report a test result, or hand work to someone else. Many changes appear in ticket history, but a short human explanation can make the reason understandable. Do not rely on a comment alone when the structured field must be updated for filters and dashboards.

An **attachment** supports a claim: screenshot, short recording or test document. It should be named so another person can find it and should avoid unnecessary personal information. **History** helps the team trace when scope, status or assignments changed and who acted. It supports accountability and later learning; it should not be used as a tool for blame.

Knowledge Sharing pages collect stable product guidance and procedures. A ticket comment is the right place for a single case's current observation. When the same question appears repeatedly, propose an article change or new page with the agreed answer. Existing pages have revisions; editors can update a page while retaining earlier versions. Project-specific pages should stay within the correct project scope.

## Step-by-Step Instructions

1. Read the ticket description, most recent comments, related links and history before posting. Check whether someone already asked the same question.
2. Decide whether you are changing the official state or supplying context. Update the correct field through your permitted action, then add a concise comment if the reason is not obvious.
3. Start a comment with the outcome or blocker. State the build/environment or requirement version if relevant, then the evidence and specific next action.
4. Attach evidence that shows the important state. Describe in the comment what the reader should look at; do not force them to infer the issue from an unnamed image.
5. If work is blocked, name the dependency, who can resolve it and when you will check again. Use a formal blocker or dependency link where the workflow supports it.
6. If a decision affects many tickets, update the authorised requirement or relevant Knowledge Sharing article after the decision is approved. Link the source ticket and verify readers in the intended project can access the page.

## Real-World PMS Examples

**QA:** “Retesting completed using the latest available test build. The original issue is no longer reproducible under the documented test conditions. No additional issues were observed during this test.” Appropriate after an actual retest, with build and environment added to the verification record; it should not stand in for the required Passed result.

**Developer:** “The reported behavior has been addressed in the current build. The ticket is ready for QA validation.” Appropriate once the corrected build is genuinely available and the QAT state can move to Ready for QAT.

**BA:** “The acceptance criteria have been clarified. Please review the updated requirements before continuing implementation.” Appropriate after the wording is recorded and any required PM change review has completed; otherwise say it is proposed and pending review.

**PM:** “This ticket remains on hold pending confirmation of the updated business requirement.” Appropriate when the decision is genuinely blocked. Add the responsible reviewer and expected follow-up so “on hold” does not become a dead end.

## Common Mistakes

- “Please fix ASAP” without a ticket link, impact or owner. Better: name the affected journey, observed impact, responsible person and requested time for an update.
- “Works for me” without build, environment or test steps. Better: state what was tested and what remains uncertain.
- Repeating the same clarification across many tickets without linking a single approved source.
- Uploading private user data or a screenshot with no explanation.
- Editing a Knowledge Sharing article as though a proposed process were already approved.

## Best Practices

Keep comments neutral, short and specific. Record a decision once in the proper ticket or article and link to it elsewhere. When an article is outdated, leave a comment or propose an edit describing the exact conflicting section and the verified current behavior. Preserve useful history and confirm that project-scoped content is seen only by authorised members.

## QA Checklist and Key Takeaways

- [ ] Recent ticket history and existing discussion checked.
- [ ] Official fields and explanation updated in the right places.
- [ ] Comment names result, context, evidence and next owner.
- [ ] Attachments are readable and free of unnecessary personal data.
- [ ] Reusable decisions are reflected in approved Knowledge Sharing content.

## Related Knowledge-Sharing Articles

Read **Getting Started with PMS**, **How to Create Professional Bug Reports and QAT Tickets**, and **How to Manage Projects, Assignments & Team Responsibilities**.
