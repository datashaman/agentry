# Agentry Workflows

This document describes the lifecycle workflows for ops requests and how agents interact with external work item providers. Work items (stories, bugs) are managed in external systems (Jira, GitHub Issues) and fetched on demand — their lifecycle state machines live in those external systems, not in Agentry.

The guiding principle for HITL design: **escalate on consequence, not confidence**. Agents escalate when the *impact* of a wrong decision is high, not merely when they are uncertain.

---

## Agent Roles

Agent roles define the classes of agents that operate across workflows. Two roles are created by default for every organization:

| Role | Purpose |
|------|---------|
| **Coding** | Implementation, branching, PR creation |
| **Review** | Diff review, approval per repo |

Additional roles can be created per organization to cover specialized workflows (triage, planning, testing, ops, monitoring, etc.).

---

## Work Item Integration

Work items are sourced from external providers configured per project:

### Jira
- Projects fetch issues via Jira Cloud API (OAuth authentication)
- Issues are filtered by status, type, and sorted by last updated
- Full issue details including assignee, priority, and direct links

### GitHub Issues
- Projects fetch issues from linked GitHub repositories
- Issues are filtered by state and labels (used as type filter)
- Pull requests are excluded from results

The **WorkItemProviderManager** resolves the correct service based on `Project.work_item_provider`.

### Event-Driven Agent Work

**EventResponders** connect agent roles to work item events. When a work item reaches a specific status, the matching event responder triggers agent work:

```
Work Item Status Change -> EventResponder Match -> DispatchAgentWork -> RunAgentWork (queued)
```

EventResponders are configured per agent role with:
- `work_item_type` — which type of work item (currently ops_request)
- `status` — which status triggers the responder
- `instructions` — specific instructions for this event context

---

## OpsRequest Lifecycle

OpsRequests are the only work item type with a local state machine, since they represent operational actions that Agentry orchestrates directly.

### State Machine

```
new -> triaged -> planning -> executing -> verifying -> closed_done
 |                                           |
 +-> closed_invalid                     closed_rejected (HITL)
```

### Phase 1: Intake

```
Request/Alert/Scheduled Trigger -> New -> Triaged
                                    |
                               Closed (Invalid)
```

1. **New** — An ops request is filed from a manual request, alert, or scheduled trigger.
2. **Triage** — Classified by category and risk level. Invalid requests are closed.

### Phase 2: Planning & Routing

The assigned agent routes the request based on its nature:

| Route | When | Flow |
|-------|------|------|
| **Code Change** | Request requires code/config changes | Opens PRs via GitHub API |
| **Direct Action** | Agent can execute directly (e.g. restart service, clear cache) | Low-risk: autonomous. High-risk/prod: HITL approval |
| **Runbook** | Complex multi-step operation, or high-risk action | Agent generates a Runbook with ordered steps. Always goes to HITL for review |

### Phase 3: Execution

```
Direct Action (low risk) -> Executing
Direct Action (high risk) -> HITL Execution Approval -> Executing
Runbook Ready -> HITL Runbook Review -> Executing
```

- **Low-risk direct actions** — Agent executes autonomously.
- **High-risk or production direct actions** — HITL approval required before execution.
- **Runbooks** — Always require human review. The human reviews the generated steps and either executes them or rejects the request.

### Phase 4: Verification

```
Executing -> Verifying -> Closed (Done)
                |
           Failed/Unexpected -> HITL Failure Review -> Rollback or Accept
```

1. **Verification** — Agent verifies the outcome matches expectations.
   - **Success** -> closed.
   - **Failure or unexpected state** -> escalates to HITL.

---

## HITL Escalation Summary

Escalations fall into two categories:

### Always-trigger (hard gates)

These are non-negotiable regardless of agent confidence:

| Escalation | Trigger |
|------------|---------|
| Runbook Review | Any runbook — human reviews and executes |
| High-Risk Ops | High/critical risk ops requests require approval |

### Consequence-triggered (soft gates)

These trigger based on the *impact* of the action, not agent uncertainty:

| Escalation | Trigger |
|------------|---------|
| Code Review | Security surface or breaking API change |
| Ops Execution | High-risk or production direct actions |
| Ops Failure | Unexpected outcome after execution |

---

## Skills in Workflows

Skills provide reusable capabilities that agent roles can leverage during workflow execution. Skills are attached to agent roles with an ordered position, allowing agents to apply specialized knowledge or procedures relevant to their current task.

Skills can be:
- **Authored locally** — created directly within the organization
- **Imported from repos** — sourced from linked repositories, tracked by path and SHA for versioning
