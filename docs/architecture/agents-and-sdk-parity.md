# Agent System and Laravel AI SDK Parity

This document defines the split between AgentType and Agent in Agentry and how it aligns with the [Laravel AI SDK](https://laravel.com/docs/12.x/ai-sdk) agent model.

---

## Overview

Agentry's agent system is designed for parity with the Laravel AI SDK. The SDK structures agents as:

- **Agent class** — instructions, tools, schema, middleware, and default config (provider, model, temperature, max_steps, etc.)
- **Prompt invocation** — overrides at runtime (provider, model, timeout)

Agentry maps this to:

- **AgentType** — the specification (class) — stable, rarely changes
- **Agent** — the instance — created per team, varies by model/provider

---

## AgentType (Specification)

AgentType represents the *class* of agent. It defines what this type of agent is and what it can do.

| Field | Purpose | SDK Equivalent |
|-------|---------|----------------|
| `name`, `slug`, `description` | Identity | — |
| `instructions` | System prompt (e.g. "You are a coding agent that implements stories") | `instructions()` |
| `tools` | Tool IDs this type can use (custom + provider tools) | `tools()` |
| `default_model` | Optional default model | `#[Model]` attribute |
| `default_provider` | Optional default provider | `#[Provider]` attribute |
| `default_temperature` | Sampling temperature (0–1) | `#[Temperature]` attribute |
| `default_max_steps` | Max tool-invocation steps | `#[MaxSteps]` attribute |
| `default_max_tokens` | Max output tokens | `#[MaxTokens]` attribute |
| `default_timeout` | HTTP timeout (seconds) | `#[Timeout]` attribute |

Tools are Laravel AI SDK tools: custom tools (your `Tool` classes) and provider tools (WebSearch, WebFetch, FileSearch). Provider tools require per-provider enablement — they are only available when the agent's provider supports them.

---

## Agent (Instance)

Agent represents a *runtime instance*. It inherits from its type and can override config.

| Field | Purpose | SDK Equivalent |
|-------|---------|----------------|
| `name` | Instance label | — |
| `agent_type_id` | Which type | — |
| `team_id` | Org structure | — |
| `model` | Required — which model to use | `prompt(..., model: ...)` |
| `provider` | Required — which provider (for tool filtering) | `prompt(..., provider: ...)` |
| `confidence_threshold` | Agentry: when to escalate to human | — |
| `status` | Runtime state (idle, active, error, busy) | — |
| `temperature` | Optional override | — |
| `max_steps` | Optional override | — |
| `max_tokens` | Optional override | — |
| `timeout` | Optional override | — |

Model and provider live on Agent because different teams/instances may use different providers and models. Provider is required for filtering provider tools by provider support.

---

## Runtime Resolution

When instantiating an SDK agent for work:

```
instructions   ← AgentType.instructions
tools          ← AgentType.tools, filtered by Agent.provider for provider tools
model          ← Agent.model ?? AgentType.default_model
provider       ← Agent.provider ?? AgentType.default_provider
temperature    ← Agent.temperature ?? AgentType.default_temperature
max_steps      ← Agent.max_steps ?? AgentType.default_max_steps
max_tokens     ← Agent.max_tokens ?? AgentType.default_max_tokens
timeout        ← Agent.timeout ?? AgentType.default_timeout
```

---

## Tool Registry

A tool registry maps tool IDs to:

- **Custom tools** — `Tool` class to instantiate
- **Provider tools** — provider support (e.g. WebSearch → [Anthropic, OpenAI, Gemini])

When resolving tools for an agent: take AgentType.tools, filter provider tools by Agent.provider, instantiate custom tools, and pass the combined list to the SDK agent.

---

## Migration from Current Model

**Remove:**
- `Agent.capabilities` (replaced by tools on type)
- `Agent.tools` (moved to type)
- `AgentType.default_capabilities` (replaced by tools)

**Add to AgentType:**
- `instructions` (text)
- `tools` (JSON array of tool IDs)
- `default_model`, `default_provider`, `default_temperature`, `default_max_steps`, `default_max_tokens`, `default_timeout` (nullable)

**Add to Agent:**
- `provider` (required, or inherit from type)
- `temperature`, `max_steps`, `max_tokens`, `timeout` (nullable overrides)

**Update Agent:**
- `model` remains (required per instance for SDK)
- Remove `tools`, `capabilities`
