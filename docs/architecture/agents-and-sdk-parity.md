# Agent System and Laravel AI SDK Parity

This document defines the split between AgentRole and Agent in Agentry and how it aligns with the [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk) agent model.

---

## Overview

Agentry's agent system is designed for parity with the Laravel AI SDK. The SDK structures agents as:

- **Agent class** — instructions, tools, schema, middleware, and default config (provider, model, temperature, max_steps, etc.)
- **Prompt invocation** — overrides at runtime (provider, model, timeout)

Agentry maps this to:

- **AgentRole** — the specification (class) — stable, rarely changes, scoped to an organization
- **Agent** — the instance — created per team, varies by model/provider

---

## AgentRole (Specification)

AgentRole represents the *class* of agent. It defines what this type of agent is and what it can do.

| Field | Purpose | SDK Equivalent |
|-------|---------|----------------|
| `name`, `slug`, `description` | Identity | — |
| `organization_id` | Org scoping | — |
| `instructions` | System prompt (e.g. "You are a coding agent that implements stories") | `instructions()` |
| `tools` | Tool IDs this type can use (custom + provider tools) | `tools()` |
| `default_model` | Optional default model | `#[Model]` attribute |
| `default_provider` | Optional default provider | `#[Provider]` attribute |
| `default_temperature` | Sampling temperature (0-1) | `#[Temperature]` attribute |
| `default_max_steps` | Max tool-invocation steps | `#[MaxSteps]` attribute |
| `default_max_tokens` | Max output tokens | `#[MaxTokens]` attribute |
| `default_timeout` | HTTP timeout (seconds) | `#[Timeout]` attribute |

AgentRoles also have:
- **EventResponders** — define which work item type + status combinations trigger agent work
- **Skills** — many-to-many relationship with ordered position, providing reusable capabilities

Tools are Laravel AI SDK tools: custom tools (your `Tool` classes) and provider tools (WebSearch, WebFetch, FileSearch). Provider tools require per-provider enablement — they are only available when the agent's provider supports them.

---

## Agent (Instance)

Agent represents a *runtime instance*. It inherits from its role and can override config.

| Field | Purpose | SDK Equivalent |
|-------|---------|----------------|
| `name` | Instance label | — |
| `agent_role_id` | Which role | — |
| `team_id` | Org structure | — |
| `model` | Required — which model to use | `prompt(..., model: ...)` |
| `provider` | Required — which provider (for tool filtering) | `prompt(..., provider: ...)` |
| `confidence_threshold` | Agentry: when to escalate to human | — |
| `status` | Runtime state (idle, active, error, busy) — system-managed, not user-editable | — |
| `temperature` | Optional override | — |
| `max_steps` | Optional override | — |
| `max_tokens` | Optional override | — |
| `timeout` | Optional override | — |
| `schedule` | Cron expression for scheduled execution | — |
| `scheduled_instructions` | Instructions used during scheduled runs | — |

Model and provider live on Agent because different teams/instances may use different providers and models. Provider is required for filtering provider tools by provider support.

---

## Runtime Resolution

When instantiating an SDK agent for work:

```
instructions   <- AgentRole.instructions
tools          <- AgentRole.tools, filtered by Agent.provider for provider tools
skills         <- AgentRole.skills (ordered by position)
model          <- Agent.model ?? AgentRole.default_model
provider       <- Agent.provider ?? AgentRole.default_provider
temperature    <- Agent.temperature ?? AgentRole.default_temperature
max_steps      <- Agent.max_steps ?? AgentRole.default_max_steps
max_tokens     <- Agent.max_tokens ?? AgentRole.default_max_tokens
timeout        <- Agent.timeout ?? AgentRole.default_timeout
```

---

## Agent Permissions

Agent permissions are configured at the **organization level** (not per-agent or per-role). The `Organization.agent_permissions` field is a JSON object that controls what actions agents may take across the following categories:

- **Branches** — create, delete
- **Pull Requests** — create, merge, close, comment
- **Code** — read, write
- **Work Items** — create, update, close, assign, comment
- **Ops Requests** — create, execute, approve
- **Milestones** — create, update, close
- **Labels** — create, update, delete
- **Deployments** — trigger, rollback

Checked via `Organization::agentCan(string $permission)`.

---

## Tool Registry

A tool registry maps tool IDs to:

- **Custom tools** — `Tool` class to instantiate
- **Provider tools** — provider support (e.g. WebSearch -> [Anthropic, OpenAI, Gemini])

When resolving tools for an agent: take AgentRole.tools, filter provider tools by Agent.provider, instantiate custom tools, and pass the combined list to the SDK agent.

---

## Tool Registry Reference

| Tool ID         | Providers              | Claude Code mapping |
|-----------------|------------------------|----------------------|
| bash            | anthropic              | Shell execution      |
| text_editor     | anthropic              | Read/edit files      |
| code_execution  | anthropic              | Sandboxed code run   |
| web_search      | anthropic, openai, gemini | Web search       |
| web_fetch       | anthropic, gemini      | Fetch URLs           |
| file_search     | openai, gemini         | Vector store search  |

---

## Default Agent Setup

When a new organization is created, Agentry automatically bootstraps:

1. **Coding** agent role
2. **Review** agent role
3. **Development** team with a **Coder** agent (Coding role) and **Reviewer** agent (Review role)
