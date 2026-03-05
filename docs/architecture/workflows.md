# Pinky Workflows

This document describes the lifecycle workflows for stories, bugs, and ops requests. Each workflow defines the states, transitions, agent responsibilities, and human-in-the-loop (HITL) escalation points.

The guiding principle for HITL design: **escalate on consequence, not confidence**. Agents escalate when the *impact* of a wrong decision is high, not merely when they are uncertain.

---

## Agent Registry

Ten specialized agent types operate across the workflows:

| Agent | Role |
|---|---|
| **Monitoring Agent** | Observes production, detects anomalies, files bugs |
| **Triage Agent** | Deduplicates, classifies, sets severity and priority |
| **Planning Agent** | Grooming, scheduling, sprint planning, unblocking |
| **Spec Critic Agent** | Reviews story quality before grooming: vague criteria, missing edge cases, contradictions |
| **Design Critic Agent** | Reviews implementation approach before coding: over-engineering, wrong abstraction, missed patterns |
| **Coding Agent** | Implementation, branching, PR creation |
| **Review Agent** | Diff review, approval per repo |
| **Test Agent** | QA, regression testing, integration verification |
| **Release Agent** | Merge, deploy, worktree cleanup |
| **Ops Agent** | Classifies ops requests, executes direct actions, generates runbooks, verifies outcomes |

---

## Story Lifecycle

### Phase 1: Discovery

```
Idea/Request → Backlog → Spec Critique → Grooming → Refined
                 ↑              ↓
                 ╰── minor ─────╯
                 ╰── substantial_change → re-critique ─╯
```

1. **Backlog** — A new story enters the backlog.
2. **Spec Critique** — The Spec Critic Agent reviews the story for quality: vague acceptance criteria, missing edge cases, internal contradictions. This is advisory — it produces a Critique record but does not block.
3. **Grooming / Refinement** — The Planning Agent reviews the critique and grooms the story:
   - **Approves** → story moves to Refined & Estimated.
   - **Rejects** → story closed as "Won't Do".
   - **Needs minor revision** → returns to Backlog for rework.
   - **Needs substantial revision** → sets `substantial_change = true` on the story, returns to Backlog. The Spec Critic re-runs on the revised spec automatically.
   - **Cross-team impact or strategic scope** → escalates to HITL Scope Review.

**HITL: Scope Review** — Triggered when a story has cross-team impact or involves strategic decisions. Human approves or rejects.

### Phase 2: Planning

```
Refined → Worktree Scan → Sprint Planned
               ↓
          WIP Surfaced → reassign or HITL
```

1. **Refined & Estimated** — Story has been groomed, estimated with story points, and approved.
2. **Worktree Scan** — Before sprint planning, the Planning Agent scans all project repos for existing worktrees. This detects stale work-in-progress from previous iterations.
   - **No conflicts** → proceeds to Sprint Planned.
   - **Stale WIP found** — WIP is surfaced to the sprint plan. The Planning Agent either reassigns the story to resume existing WIP, or escalates if the WIP conflicts.

**HITL: Sprint WIP Review** — Triggered when stale WIP in a repo conflicts with a story being planned for the sprint. Human decides whether to discard, defer, or resolve.

### Phase 3: Development

```
Sprint Planned → Design Critique → Worktree Check → In Development → ChangeSet → PRs Opened
                                        ↓
                                   Resume / Create / HITL
```

1. **Design Critique** — The Design Critic Agent reviews the implementation approach before coding begins. Advisory — produces a Critique record.
2. **Worktree Check** — The Coding Agent checks for existing worktrees for this work item:
   - **None found** → creates fresh worktrees (one per affected repo).
   - **Stale/interrupted WIP for this work item** → resumes from last commit.
   - **Stale WIP for a *different* work item** → escalates to HITL.
3. **In Development** — The Coding Agent implements the story. It identifies all affected repos and creates:
   - A **ChangeSet** grouping the work.
   - One **branch** per affected repo.
   - One **PR** per branch.
4. **Blocked** — If the story is blocked by a dependency, the Planning Agent attempts resolution. Cross-team or external blockers escalate to HITL.

**HITL: Worktree Conflict** — Stale WIP for a different work item occupies a repo needed for the current story. Human decides: discard stale WIP or finish it first.

**HITL: Block Resolution** — Cross-team dependency or external blocker that the Planning Agent cannot resolve autonomously.

### Phase 4: Review

```
PRs Opened → Code Review (per repo) → ChangeSet Ready → QA → Staging
                   ↓                                      ↓
              Changes needed → Design Critique re-run    Files bug
```

1. **Code Review** — The Review Agent reviews each PR independently, per repo.
   - **Any PR needs changes** → Design Critic re-runs (always on loop-back), then returns to In Development.
   - **All PRs approved** → ChangeSet marked ready.
   - **Security surface or breaking API change** → escalates to HITL.
2. **QA / Integration Testing** — The Test Agent runs integration tests against the change set.
   - **Passes** → moves to Staging/UAT.
   - **Regression found** → files a Bug and returns to In Development.
3. **Staging / UAT** — Final verification in staging environment.

**HITL: Code Review** — Triggered by security-surface changes or breaking API modifications. Human reviews and approves or sends back.

### Phase 5: Release

```
Staging → Merged → Deployed → Worktrees Cleaned → Closed (Done)
   ↓
  HITL (major version / infra change)
```

1. **Merge** — The Release Agent merges all PRs in the ChangeSet to main.
   - Patch/minor changes proceed autonomously.
   - Major version bumps or infrastructure changes escalate to HITL.
2. **Deploy** — The Release Agent deploys to production.
3. **Worktree Cleanup** — All worktrees and feature branches for this story are removed.
4. **Closed (Done)** — Story is complete.

**HITL: Release Approval** — Major version or infrastructure changes require human sign-off before merge.

---

## Bug Lifecycle

### Phase 1: Intake

```
Report/Alert/Monitoring → New → Triaged
                           ↓
                      Duplicate / Can't Reproduce / HITL
```

1. **New** — A bug is filed, either by the Monitoring Agent (from alerts) or manually.
2. **Triage** — The Triage Agent evaluates:
   - **Duplicate** → closed.
   - **Can't reproduce** → closed.
   - **Triageable** → sets severity and priority, moves to Triaged.
   - **Data loss, security, or ambiguous P0** → escalates to HITL.

**HITL: Triage Review** — Potential data loss, security vulnerability, or ambiguous P0 severity requires human classification.

### Phase 2: Prioritization

```
Triaged → P0/Critical → HITL P0 Sign-off → Hotfix Queue
        → Normal → Sprint
        → Low → Bug Backlog → Sprint (later)
```

1. **P0 / Critical** — Enters hotfix queue. **Always** requires HITL sign-off before work begins.
2. **Normal priority** — Added to sprint by Planning Agent.
3. **Low priority** — Goes to bug backlog for later prioritization.

**HITL: P0 Sign-off** — Always triggered for P0 bugs. No autonomous P0 work begins without human approval.

### Phase 3: Fix

```
HITL / Sprint → Worktrees Created → In Progress → ChangeSet → PRs Opened
                                        ↓
                                     Blocked → Planning Agent / HITL
```

Follows the same pattern as story development:
- Worktrees created (one per affected repo).
- Coding Agent implements the fix.
- ChangeSet and PRs created across affected repos.

### Phase 4: Verification

```
PRs Opened → Code Review (per repo) → ChangeSet Ready → QA Verification → Released
                                                              ↓
                                                          Not fixed → back to In Progress
```

1. **Code Review** — Per-repo review by the Review Agent. Changes needed sends back to In Progress.
2. **QA Verification** — Test Agent verifies the fix.
   - **Verified** → staged for release.
   - **Not fixed** → returns to In Progress.
3. **Release** — Normal fixes merge and deploy autonomously. P0 hotfixes always escalate to HITL.
4. **Worktree Cleanup** — Worktrees and branches removed after deployment.

**HITL: Hotfix Deploy Approval** — Always triggered for P0 fixes. No autonomous P0 production push.

### Cross-flow Links

- **QA failure in story flow** → Test Agent files a Bug, entering the bug intake flow.
- **Bug closed** → Planning Agent checks if the fix unblocks any stories, resuming their development.

---

## OpsRequest Lifecycle

### Phase 1: Intake

```
Request/Alert/Scheduled Trigger → New → Triaged
                                   ↓
                              Closed (Invalid/Duplicate)
```

1. **New** — An ops request is filed from a manual request, alert, or scheduled trigger.
2. **Triage** — The Triage Agent classifies by category and risk level. Invalid or duplicate requests are closed.

### Phase 2: Planning & Routing

The Ops Agent routes the request based on its nature:

```
Triaged ──→ Code Change path     → normal PR lifecycle
        ──→ Direct Action path   → execution
        ──→ Runbook path         → runbook generation → HITL review
```

| Route | When | Flow |
|---|---|---|
| **Code Change** | Request requires code/config changes | Opens a ChangeSet, follows the standard PR review pipeline |
| **Direct Action** | Agent can execute directly (e.g. restart service, clear cache) | Low-risk: autonomous. High-risk/prod: HITL approval |
| **Runbook** | Complex multi-step operation, or high-risk action | Agent generates a Runbook with ordered steps. Always goes to HITL for review and execution |

### Phase 3: Execution

```
Direct Action (low risk) → Executing
Direct Action (high risk) → HITL Execution Approval → Executing
Runbook Ready → HITL Runbook Review → Executing
```

- **Low-risk direct actions** — Ops Agent executes autonomously.
- **High-risk or production direct actions** — HITL approval required before execution.
- **Runbooks** — Always require human review. The human reviews the generated steps and either executes them or rejects the request.

**HITL: Execution Approval** — High-risk or production-targeting direct actions.

**HITL: Runbook Review + Execution** — Always triggered. Human reviews the generated runbook steps and executes or rejects.

### Phase 4: Verification

```
Executing → Verifying → Closed (Done)
                ↓
           Failed/Unexpected → HITL Failure Review → Rollback or Accept
```

1. **Verification** — Ops Agent verifies the outcome matches expectations.
   - **Success** → closed.
   - **Failure or unexpected state** → escalates to HITL.

**HITL: Failure Review** — Unexpected outcome after ops execution. Human decides to rollback and retry, or accept the current state.

---

## HITL Escalation Summary

Escalations fall into two categories:

### Always-trigger (hard gates)

These are non-negotiable regardless of agent confidence:

| Escalation | Trigger |
|---|---|
| P0 Sign-off | Any P0 bug — no autonomous P0 start |
| Hotfix Deploy | Any P0 fix — no autonomous P0 prod push |
| Runbook Review | Any runbook — human reviews and executes |

### Consequence-triggered (soft gates)

These trigger based on the *impact* of the action, not agent uncertainty:

| Escalation | Trigger |
|---|---|
| Scope Review | Cross-team impact or strategic scope |
| Block Resolution | External or cross-team dependency |
| Code Review | Security surface or breaking API change |
| Release Approval | Major version or infrastructure change |
| Triage Review | Data loss, security, or ambiguous P0 |
| Ops Execution | High-risk or production direct actions |
| Ops Failure | Unexpected outcome after execution |
| Worktree Conflict | Stale WIP for different work item in same repo |
| Sprint WIP Review | Stale WIP conflicts with sprint plan |

---

## State Machines

### Story States

```
backlog → refined → sprint_planned → in_development → in_review → staging → merged → deployed → closed_done
                                          ↓                ↑
                                       blocked ────────────╯
backlog → closed_wont_do
```

### Bug States

```
new → triaged → in_progress → in_review → verified → released → closed_fixed
 ↓                  ↓            ↑
 ├→ closed_duplicate blocked ────╯
 ╰→ closed_cant_reproduce
```

### OpsRequest States

```
new → triaged → planning → executing → verifying → closed_done
 ↓                                        ↓
 ╰→ closed_invalid                   closed_rejected (HITL)
```

---

## Worktree Lifecycle

Worktrees are the physical bridge between work items and code. Their lifecycle is tightly coupled to work item state:

```
(work item enters development)
    → Check for existing worktrees
        → None: Create fresh (1 per affected repo)
        → Same work item WIP: Resume from last commit
        → Different work item WIP: HITL Worktree Conflict

(during development)
    → Active: agent is working
    → Interrupted: agent pulled away (interrupted_at + reason recorded)

(work item completed)
    → Cleaned up: worktrees and branches deleted by Release Agent
```

Key fields:
- `status` — active, interrupted, stale
- `interrupted_at` / `interrupted_reason` — enables clean context restoration when resuming work
- `last_activity_at` — used by Planning Agent to detect stale worktrees during sprint planning
