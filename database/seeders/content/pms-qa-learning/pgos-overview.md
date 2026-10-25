## Overview

PGOS (Personal Goal Operating System) helps a person plan and review meaningful goals. It is more than a list of errands: a goal has an outcome, milestones show intermediate results, and actions describe the work needed to reach them. The application stores goal information locally and provides progress, deadline, reminder and review features. A useful PGOS experience helps the user decide what to do next, see whether the plan is working, and adjust it when circumstances change.

## Who Should Read This

Quality Assurance (QA) Engineers, Business Analysts (BAs), and Project Managers (PMs) working on PGOS. No programming knowledge is needed. Read this before testing individual screens or writing goal-related requirements.

## Learning Objectives

- Explain the difference between a goal, milestone and action.
- Follow a user from initial plan through review and adjustment.
- Judge whether progress information helps a user make a decision.
- Identify product questions that need clarification before testing.

## Key Concepts Explained

**Goal:** An intended result, such as improving English communication over three months. It should say what success means, not merely name an activity. **Milestone:** A measurable checkpoint on the way to that result. **Action:** A task the user can perform, such as a weekly speaking session. Completing an action is useful only if it contributes to the goal; completing a milestone provides evidence of a larger outcome.

**Priority** helps users decide which goals need attention when time is limited. **Progress** summarizes advancement, but its meaning depends on the product's configured calculation. QA should confirm the applicable rule before asserting a percentage or status. **Deadline** expresses when work should be done. **Reminder** draws attention to planned work; it should not silently alter the plan. **Review** is the point at which the user reflects on results and changes future steps. Goal health information is valuable when it explains the reason for concern and suggests a sensible next action.

PGOS aims to solve familiar problems: vague ambitions, forgotten commitments, and lack of feedback about whether effort is producing results. Its users may be learning a skill, improving health, or completing a long personal project. They should be able to resume an existing plan without recreating it and understand a missed step without being misled into thinking the entire goal has failed.

## Step-by-Step User Journey

1. **Create a goal.** Enter a clear outcome and the dates or priority that matter. The saved goal should be easy to recognise later.
2. **Define milestones.** Break the outcome into checkpoints with a meaningful way to tell whether each is complete.
3. **Create actions.** Plan small, realistic steps under the relevant milestone or goal. The user should know which action to take next.
4. **Monitor progress.** Complete work and compare the displayed progress with the actual completed milestones and actions. The summary should remain understandable after the app is reopened.
5. **Respond to reminders.** Inspect the reminder in context. The user should be able to find the relevant goal and decide whether to act, postpone, or revise the plan according to available controls.
6. **Perform a review.** Compare actual work with the intended outcome, record what helped or blocked progress, and look for a realistic adjustment.
7. **Update the plan.** Change future actions or milestones and confirm that the new plan and historical progress remain coherent.

## Real-World PGOS Example

Imagine a user who wants to communicate more confidently in English after three months. They create the goal with that outcome. Month one might have a milestone to complete four short conversations; month two might focus on presenting a five-minute topic; month three might involve leading a meeting discussion. Weekly actions could include practice with a partner, listening exercises, and a short speaking diary. After completing an action, the user checks whether the relevant milestone and goal summary respond according to the documented rule.

If a week of practice is missed, the user should see the gap and choose a sustainable response. They might reschedule two sessions and reduce a later milestone's scope. A review can compare confidence and completed conversations with the original aim. The product succeeds if the user understands what was achieved and can make a better plan for the remaining time.

## Common Mistakes

- Treating every completed action as proof that the overall goal is achieved.
- Assuming a progress percentage without checking the configured calculation.
- Testing only the creation button while ignoring reopening, missed deadlines and reviews.
- Calling a reminder failure when device notification permission or scheduling conditions were not checked.

## Best Practices

Use a realistic goal with at least two milestones and several actions during testing. Record the starting state before changing completion or dates. Ask the BA when success criteria or progress rules are unclear. Evaluate the words and visual signals from a new user's perspective: can the person explain what happened and what to do next?

## QA Checklist and Key Takeaways

- [ ] Can a user create and later find the same goal?
- [ ] Are milestones and actions connected to the intended outcome?
- [ ] Does progress reflect the documented rule after edits and reopening?
- [ ] Are missed deadlines and reminders understandable?
- [ ] Can a review lead to a practical change in the plan?

## Related Knowledge-Sharing Articles

Continue with **How to Test PGOS**, **How to Perform Requirement Reviews, Retesting & Regression Testing**, and **How to Create Professional Bug Reports and QAT Tickets** in this learning center.
