# Agentry Object Model

This document describes the domain object model for Agentry — a project management platform where AI agents perform software engineering work under human oversight.

---

## Domain Overview

Agentry organizes work across **organizations**, **teams**, and **projects**. AI **agents** of various roles are assigned to teams and carry out work sourced from external providers (Jira, GitHub Issues). All code is managed through linked **repos**. A human-in-the-loop escalation system ensures agents can surface uncertainty, and a critique system enables iterative quality improvement.

---

## Object Model

### Organizational Structure

```
Organization
├── Team[]
│   └── Agent[]
├── AgentRole[]
│   └── Skill[]
├── Skill[]
└── Project[]
    ├── Repo[]
    ├── WorkItem[]
    └── OpsRequest[]
```

**Organization** is the top-level tenant. It owns projects, teams, agent roles, and skills. It also holds GitHub App installation details and an **agent permissions** system that controls what actions agents can take (branches, pull requests, code, work items, ops requests, deployments).

**Team** groups agents together. Teams are assigned to projects, establishing which agents can work on which codebases.

**Project** is the primary container for work. It holds repos, tracked work items, and ops requests. Projects connect to external work item providers (Jira or GitHub Issues) for stories, bugs, and tasks. Users selectively track specific issues as **WorkItems** for agents to work on.

---

### Agent System

```
AgentRole (1) ──── (N) Agent
                        ├── model: string
                        ├── provider: string
                        ├── confidence_threshold: float
                        ├── temperature: float?
                        ├── max_steps: int?
                        ├── max_tokens: int?
                        ├── timeout: int?
                        ├── status: string
                        ├── schedule: string?
                        └── scheduled_instructions: string?

AgentRole
├── instructions: text
├── tools: json
├── default_model, default_provider, default_temperature
├── default_max_steps, default_max_tokens, default_timeout
├── EventResponder[]
└── Skill[] (many-to-many, ordered by position)
```

**AgentRole** (formerly AgentType) defines a class of agent with a set of responsibilities, instructions (system prompt), and tools. It also specifies default runtime config that agents can inherit or override. Agent roles are scoped to an organization.

**Agent** is a runtime instance of a role, configured with a specific model, provider, and confidence threshold. Agents can optionally override temperature, max_steps, max_tokens, and timeout from their role defaults. Agents also support scheduled execution via `schedule` and `scheduled_instructions`.

**EventResponder** links an agent role to specific work item types and statuses, defining which events trigger agent work.

---

### Skills

```
Skill
├── name, slug, description
├── content: text
├── source_repo_id: string?      ← imported from a repo
├── source_path: string?
├── source_sha: string?
├── frontmatter_metadata: json
├── resource_paths: json
└── AgentRole[] (many-to-many, ordered by position)
```

**Skill** represents a reusable capability that can be attached to agent roles. Skills can be authored locally or imported from a linked repo (tracked via source_repo_id, source_path, and source_sha). Skills are scoped to an organization and can be exported/shared.

---

### Work Items (External Providers)

Work items (stories, bugs, tasks) are **not stored locally**. Instead, they are fetched on demand from external providers via the **WorkItemProvider** contract.

```
WorkItemProvider (interface)
├── JiraService        ← fetches from Jira Cloud via OAuth
└── GitHubIssuesService ← fetches from GitHub Issues API
```

Each **Project** specifies its `work_item_provider` (`jira` or `github`) and `work_item_provider_config` (provider-specific settings like project key or repo).

The **WorkItemProviderManager** resolves the correct provider service based on a project's configuration.

Work items returned by providers are normalized to a common shape:

| Field | Description |
|-------|-------------|
| `key` | Provider-specific identifier (e.g. PROJ-123, #42) |
| `title` | Work item title |
| `type` | Issue type (story, bug, task, etc.) |
| `status` | Current status |
| `priority` | Priority level |
| `assignee` | Assigned user |
| `url` | Link back to the provider |
| `created_at` | Creation timestamp |
| `updated_at` | Last update timestamp |

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
├── Runbook[]
│   └── RunbookStep[]
├── ActionLog[]
└── HitlEscalation[]
```

**OpsRequest** represents an operational action — a deployment, infrastructure change, configuration update, or data migration. OpsRequests have a state machine with validated transitions.

- **Execution type** determines the level of human involvement: fully automated, supervised (agent executes, human watches), or manual (human executes with agent guidance).
- **Risk level** drives approval requirements and escalation thresholds. High/critical risk ops requests require HITL approval.
- A **Runbook** may be generated with ordered **RunbookSteps**, tracking execution status.

---

### Git Integration

```
Repo
├── name: string
├── url: string
├── primary_language: string
├── default_branch: string
├── tags: string[]
├── github_webhook_id: string?
└── OpsRequest[] (many-to-many)
```

**Repo** represents a linked GitHub repository. Repos are browsed and linked from the organization's GitHub App installation. Worktrees, branches, changesets, pull requests, and reviews are managed directly via the GitHub API rather than stored locally.

---

### Quality & Oversight

#### Critiques

```
Critique
├── work_item_key: string       ← external work item reference
├── critic_type: string         ← spec / code / test / design
├── revision: int
├── issues: string[]
├── questions: string[]
├── recommendations: string[]
├── severity: string            ← blocking / major / minor / suggestion
├── disposition: string         ← pending / accepted / rejected / deferred
├── supersedes_id: string?
└── Agent (authored by)
```

**Critique** enables iterative quality improvement. Critiques are authored by agents against work items. Revision tracking and supersession chains maintain review history.

#### Human-in-the-Loop Escalations

```
HitlEscalation
├── trigger_type: string        ← confidence / risk / policy / ambiguity
├── trigger_class: string
├── agent_confidence: float
├── reason: string
├── work_item_type: string      ← polymorphic
├── work_item_id: string
├── Agent (raised by)
├── resolution: string?
├── resolved_by: string?
└── resolved_at: date?
```

**HitlEscalation** is raised by an agent when it cannot proceed autonomously. Escalations block progress on the associated work item until a human resolves them.

---

### Audit Trail

```
ActionLog
├── agent_id: string
├── action: string
├── reasoning: string
└── timestamp: date
```

**ActionLog** records every significant action taken by an agent, including the agent's reasoning. ActionLogs are polymorphically attached to work items.

---

### Attachments

```
Attachment
├── work_item_type: string      ← polymorphic
├── work_item_id: string
├── filename: string
├── path: string
├── mime_type: string
└── size: int
```

**Attachment** stores files associated with work items (e.g. screenshots, logs).

---

## Key Invariants

1. **An agent cannot work on a project unless its team is assigned to that project.**
2. **An unresolved HITL escalation blocks progress on its associated work item.**
3. **A critique with "blocking" severity and "pending" disposition prevents its work item from being marked complete.**
4. **Ops requests with risk level "high" or "critical" always require HITL approval before execution.**
5. **OpsRequest status transitions are validated by a state machine — invalid transitions throw exceptions.**
6. **Agent roles and skills are scoped to an organization.**
7. **Agent permissions are configured at the organization level and govern what actions agents may take.**
