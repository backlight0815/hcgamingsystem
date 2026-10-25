## Overview

PMS uses status to show where a ticket is in its workflow, priority to show urgency, blockers to explain stopped work, and resolution to record an outcome or reason. These fields answer different questions. A Critical ticket can still be waiting for QA, and a Low priority ticket can be blocked. Keeping the distinctions clear helps managers and testers make sound decisions.

## Who Should Read This

QA Engineers, Developers and Project Managers (PMs) who investigate, transition or review Quality Assurance Tickets (QAT).

## Learning Objectives

- Explain the main QAT lifecycle and who normally acts next.
- Use priority, blockers and resolution fields for their intended purpose.
- Understand why a failed retest leads back to active work.
- Identify conditions that prevent a transition.

## Key Concepts Explained

**Status** shows the current stage. **Priority** expresses urgency relative to other work. **Blocker** identifies a condition that prevents progress, such as an unavailable test environment or unresolved dependency. **Resolution** records why a ticket is concluded or cannot be tested as originally planned. The team's verified priority labels are **Critical (P1)**, **High (P2)**, **Normal (P3)** and **Low (P4)**. The actual impact, number of affected users and availability of a workaround should guide selection.

The common current QAT path is as follows. The status is not a statement that the problem is fixed unless the relevant verification has been recorded:

| Status | Meaning, next owner and common mistake |
| --- | --- |
| Open | New QAT work is available for triage or assignment. A manager should confirm ownership; do not leave it unassigned indefinitely. |
| In Progress | An assigned developer is investigating or changing the behavior. Next, the developer may request Code Review or Ready for QAT; avoid treating investigation as completion. |
| Code Review | A change is being reviewed. The developer or reviewer must address feedback; do not send it to QA before the build is ready. |
| Ready for QAT | The implementation is available for QA. An assigned QA Engineer starts testing; do not use this status when the build or prerequisite is missing. |
| QAT in Progress | QA is actively validating the reported behavior. Record build and environment; do not jump directly to QAT Done. |
| QAT Done | QA recorded a passed verification. A manager coordinates the next production step; this is not the same as a live deployment. |
| Production Done | The manager records production completion after the required checks. Do not use it merely because QA passed. |
| Reopen | A reasoned return to development after failure or new evidence. The assigned developer investigates again; do not reopen without an explanation. |

PMS also accepts older QAT stages for existing tickets, including New, Triaged, Assigned, Fixed, Ready for Retest, Retesting, Verified, Closed, Reopened, Blocked, Duplicate and Rejected. Their transitions differ from the current path. Read the ticket's available action before applying an old-stage instruction. A separate blocker or hold record can pause work; do not assume **On Hold** is a standard main QAT status.

## Step-by-Step Instructions

1. Read the current ticket status, assignments and recent history before changing it.
2. Check whether a developer, QA Engineer or PM must act next. Some transitions are restricted to the assigned person or manager.
3. Check unresolved QAT dependencies. PMS prevents certain retest and completion transitions while prerequisites remain unresolved.
4. When QA starts validation, record the test build, environment and result in the verification action. A **Passed** result moves a current QAT from QAT in Progress to QAT Done. A **Failed** result moves it to Reopen.
5. If the issue remains, provide a reason and new evidence. The developer then investigates and returns it through review and QA preparation.
6. Record production completion only through the appropriate manager action after the release work is complete.

## Real-World PGOS Example

QA reports an illustrative milestone-progress problem as Open. A manager assigns the developer and QA owner. The developer investigates, moves to In Progress, then Code Review and Ready for QAT when the change is available. The QA owner starts QAT in Progress and verifies the updated build. If the displayed progress now follows the agreed rule, QA records Passed and the ticket reaches QAT Done. If it still fails, QA records Failed with evidence; the ticket becomes Reopen. Production Done is a later milestone coordinated by the authorised manager, after delivery and any required release checks.

Priority examples: Critical might involve a widespread inability to access essential goal data with no workaround; High might affect a key goal workflow for many users; Normal might be a reproducible but limited progress-display error; Low might be a minor presentation issue with an easy workaround. These are examples, not automatic mappings.

PMS resolution values include **Environment Issue**, **Deployment Issue**, **Missing Requirements**, **Existing Behaviour**, **Not Bug** and **Unable to Test**. Use Environment Issue when the test setup caused the observation; Deployment Issue when an incorrect or incomplete build is the cause; Missing Requirements when expected behavior cannot yet be determined; Existing Behaviour when the observed behavior is established; Not Bug when the evidence shows no defect under agreed rules; and Unable to Test when validation cannot be performed. Add a comment explaining the evidence behind the choice.

## Common Mistakes

- Treating urgency, stage, blocker and resolution as interchangeable labels.
- Marking QAT Done without a passed QA verification.
- Choosing Critical without evidence of scope or impact.
- Reopening without a reason or leaving a blocker without an owner and next action.
- Assuming a ticket in QAT Done has already reached production.

## Best Practices

Keep status updates timely and attach clear evidence. When a blocker exists, state what is needed, who can provide it and when to review it. Describe priority in user terms and revisit it if the impact changes. Consult the ticket's actual available transitions; role and dependency checks are enforced by PMS.

## QA Checklist and Key Takeaways

- [ ] Current status and assignments checked.
- [ ] Priority supported by impact and urgency.
- [ ] Dependencies or blockers documented with a next action.
- [ ] Verification result includes build, environment and evidence.
- [ ] QAT completion and production completion are kept distinct.

## Related Knowledge-Sharing Articles

Read **How to Create Professional Bug Reports and QAT Tickets**, **Understanding Development Cycles, Release Management & QA Testing**, and **Professional Team Collaboration**.
