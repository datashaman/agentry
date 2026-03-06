# Agentry Object Model

This document describes the domain object model for Agentry — a project management platform where AI agents perform software engineering work under human oversight.

---

## Domain Overview

Agentry organizes work across **organizations**, **teams**, and **projects**. AI **agents** of various types are assigned to teams and carry out work items: **stories**, **bugs**, and **ops requests**. All code changes flow through **repos**, **branches**, **pull requests**, and **reviews**. A human-in-the-loop escalation system ensures agents can surface uncertainty, and a critique system enables iterative quality improvement.

---

## Object Model

### Organizational Structure

```
Organization
├── Team[]
│   └── Agent[]
└── Project[]
    ├── Repo[]
    ├── Epic[]
    ├── Milestone[]
    ├── Bug[]
    └── OpsRequest[]
```

**Organization** is the top-level tenant. It owns projects and contains teams.

**Team** groups agents together. Teams are assigned to projects, establishing which agents can work on which codebases.

**Project** is the primary container for all work. It holds repos, epics, milestones, bugs, and ops requests.

---

### Agent System

```
AgentType (1) ──── (N) Agent
                        ├── confidence_threshold: float
                        ├── model: string
                        ├── tools: string[]
                        └── capabilities: string[]
```

**AgentType** defines a class of agent (e.g. "developer", "reviewer", "ops-executor") with a set of responsibilities. Each **Agent** is an instance of a type, configured with a specific model, toolset, capabilities, and a confidence threshold that governs when it must escalate to a human.

Agents are the actors in the system. They are assigned to stories, tasks, bugs, and ops requests. They author pull requests, submit reviews, raise escalations, and produce critiques.

---

### Work Item Hierarchy

```
Epic
└── Story[]                     ← assignable to Agent
    ├── Task[]                  ← assignable to Agent
    │   └── Subtask[]
    ├── Label[]
    ├── Milestone?
    ├── Dependency[]            ← blocked-by relationships
    ├── ChangeSet[]
    ├── ActionLog[]
    ├── Attachment[]
    ├── Critique[]
    └── HitlEscalation[]
```

**Epic** is a large body of work that decomposes into stories.

**Story** is the primary work item. It has:
- **Status** and **priority** for workflow tracking
- **Story points** for estimation
- **Due date** for scheduling against milestones
- **Spec revision count** and **substantial change flag** — tracking how many times the spec has been revised and whether changes are material (triggering re-review)
- **Dev iteration count** — how many development cycles the story has been through

**Task** breaks a story into discrete implementation steps. Tasks have a **type** (e.g. "code", "test", "config"). **Subtask** further decomposes tasks.

**Dependency** models blocked-by relationships between stories and bugs, enabling the system to sequence work correctly.

---

### Bug Tracking

```
Bug
├── severity: string            ← critical / major / minor / trivial
├── priority: string
├── environment: string
├── repro_steps: string
├── linked_story_id: string?    ← optional link to originating story
├── Label[]
├── Milestone?
├── Dependency[]
├── ChangeSet[]
├── ActionLog[]
├── Attachment[]
├── Critique[]
└── HitlEscalation[]
```

**Bug** is a defect record. It can be linked to the story that introduced it, tagged with labels, and targeted at a milestone. Bugs follow the same change-set and review workflow as stories.

---

### Ops Requests

```
OpsRequest
├── category: string            ← deployment / infrastructure / config / data
├── execution_type: string      ← automated / supervised / manual
├── risk_level: string          ← low / medium / high / critical
├── environment: string
├── scheduled_at: date?
├── Repo[]                      ← scoped to specific repos
├── Story[]                     ← linked work items
├── Bug[]                       ← linked bugs
├── ChangeSet?
├── Runbook?
├── ActionLog[]
└── HitlEscalation[]
```

**OpsRequest** represents an operational action — a deployment, infrastructure change, configuration update, or data migration. Key design points:

- **Execution type** determines the level of human involvement: fully automated, supervised (agent executes, human watches), or manual (human executes with agent guidance).
- **Risk level** drives approval requirements and escalation thresholds.
- A **Runbook** may be generated with ordered steps, tracking execution status and who executed it.
- **ChangeSet** captures any code or configuration changes produced by the ops request.

---

### Git Integration

```
Repo
├── primary_language: string
├── default_branch: string
├── tags: string[]
├── Worktree[]
│   ├── path: string
│   ├── status: string
│   ├── work_item_id: string    ← polymorphic: Story | Bug | OpsRequest
│   ├── last_activity_at: date
│   ├── interrupted_at: date?
│   └── interrupted_reason: string?
└── Branch[]
    ├── work_item_id: string    ← polymorphic: Story | Bug | OpsRequest
    ├── base_branch: string
    └── PullRequest?
```

**Repo** represents a git repository with metadata about its language and tagging.

**Worktree** is a checked-out working copy of a repo. Each worktree is linked to exactly one branch and one work item. The **interrupted_at** and **interrupted_reason** fields support context-switching — when an agent is pulled off a task, the worktree records why and when, enabling clean resumption later.

**Branch** links a git branch to its work item and base branch. A branch may have an associated pull request.

---

### Code Review Pipeline

```
Story / Bug
└── ChangeSet[]
    ├── status: string          ← draft / ready / merged / reverted
    ├── summary: string
    └── PullRequest[]
        ├── status: string      ← open / approved / merged / closed
        ├── Branch (from)
        ├── Repo (targets)
        ├── Agent (authored by)
        └── Review[]
            ├── Agent (submitted by)
            └── ...
```

**ChangeSet** groups one or more pull requests that together implement a story or fix a bug. This allows multi-repo changes to be tracked as a unit.

**PullRequest** is authored by an agent, targets a repo, and requires one or more reviews.

**Review** is submitted by an agent (which may be a specialized reviewer agent or a human-proxy agent).

---

### Quality & Oversight

#### Critiques

```
Critique
├── work_item_id: string        ← polymorphic: Story | Bug
├── critic_type: string         ← spec / code / test / design
├── revision: int               ← which revision this critique targets
├── issues: string[]
├── questions: string[]
├── recommendations: string[]
├── severity: string            ← blocking / major / minor / suggestion
├── disposition: string         ← pending / accepted / rejected / deferred
├── supersedes_id: string?      ← links to previous critique it replaces
└── Agent (authored by)
```

**Critique** enables iterative quality improvement. Critiques are authored by agents (often a dedicated reviewer or QA agent) against stories or bugs. Key design points:

- **Revision tracking** — each critique targets a specific revision of the work item, preventing stale feedback.
- **Supersession chain** — a critique can supersede a previous one, creating a linked history of review iterations.
- **Disposition** — work item owners can accept, reject, or defer critique items.

#### Human-in-the-Loop Escalations

```
HitlEscalation
├── trigger_type: string        ← confidence / risk / policy / ambiguity
├── trigger_class: string       ← specific trigger classification
├── agent_confidence: float     ← agent's confidence at time of escalation
├── reason: string
├── work_item_id: string        ← polymorphic: Story | Bug | OpsRequest
├── Agent (raised by)
├── resolution: string?
├── resolved_by: string?        ← human identifier
├── created_at: date
└── resolved_at: date?
```

**HitlEscalation** is raised by an agent when it cannot proceed autonomously. Escalation triggers include:

- **Confidence** — agent's confidence drops below its threshold
- **Risk** — action exceeds the agent's risk tolerance
- **Policy** — organizational policy requires human approval
- **Ambiguity** — requirements are unclear or contradictory

Escalations block progress on the associated work item until a human resolves them.

---

### Audit Trail

```
ActionLog
├── agent_id: string
├── action: string
├── reasoning: string
└── timestamp: date
```

**ActionLog** records every significant action taken by an agent on a work item, including the agent's reasoning. This provides a complete audit trail for debugging agent behavior and understanding decision-making.

---

## Polymorphic Relationships

Several entities use polymorphic relationships via `work_item_id` + `work_item_type`:

| Entity | Polymorphic Target |
|---|---|
| Worktree | Story, Bug, OpsRequest |
| Branch | Story, Bug, OpsRequest |
| ActionLog | Story, Task, Bug, OpsRequest |
| HitlEscalation | Story, Bug, OpsRequest |
| Critique | Story, Bug |
| ChangeSet | Story, Bug, OpsRequest |

---

## Key Invariants

1. **An agent cannot work on a project unless its team is assigned to that project.**
2. **A work item blocked by unresolved dependencies cannot transition to "in progress".**
3. **An unresolved HITL escalation blocks progress on its associated work item.**
4. **A critique with "blocking" severity and "pending" disposition prevents its work item from being marked complete.**
5. **Ops requests with risk level "high" or "critical" always require HITL approval before execution.**
6. **A worktree is linked to exactly one branch and one work item at any given time.**
7. **Superseded critiques are immutable — only the latest non-superseded critique in a chain is actionable.**
