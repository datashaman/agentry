# Agentry - Agents & SDK Parity

This PRD implements the agent system migration to align with the Laravel AI SDK. AgentType becomes the specification (instructions, tools, default config), org-scoped; Agent becomes the runtime instance (model, provider, optional overrides). See [agents-and-sdk-parity.md](../architecture/agents-and-sdk-parity.md).

---

## User Stories

### US-001 - AgentType Organization Scoping

**As** an organization administrator  
**I want** agent types to be scoped by organization  
**So that** each org can define its own roles independently.

**Acceptance criteria:**
- agent_types table has organization_id (foreign key, required)
- Slug unique per organization (not globally)
- AgentType model: belongsTo Organization, fillable includes organization_id
- All agent type queries filtered by current user's organization
- Agent type create form: organization pre-filled from current org context
- Agent type list, create, edit, detail: require org context; show No Organization fallback when none
- Feature test: agent types are org-scoped; user sees only their org's types

**Priority:** 1  
**Status:** Done

---

### US-002 - AgentType Instructions and Tools

**As** a platform administrator  
**I want** agent types to define instructions (system prompt) and tools (tool IDs)  
**So that** the specification lives on the type rather than per-agent.

**Acceptance criteria:**
- agent_types table has instructions (text, nullable) and tools (json, nullable) columns
- default_capabilities column removed; existing data migrated to tools (as tool IDs)
- AgentType model: fillable and casts updated for instructions and tools
- Agent types UI (create, edit, detail) shows instructions and tools instead of default_capabilities
- Feature test: create/edit agent type with instructions and tools

**Priority:** 2  
**Status:** Done

---

### US-003 - AgentType Default Config

**As** a platform administrator  
**I want** agent types to define default model, provider, and runtime config  
**So that** agents can inherit or override.

**Acceptance criteria:**
- agent_types table has: default_model, default_provider, default_temperature, default_max_steps, default_max_tokens, default_timeout (all nullable)
- AgentType model fillable and casts updated
- Agent types UI: optional fields for default config in create/edit forms
- Feature test: create agent type with default config values

**Priority:** 3  
**Status:** Done

---

### US-004 - Agent Provider and Overrides

**As** a platform administrator  
**I want** agents to define provider (required) and optional runtime overrides  
**So that** different teams can use different providers and models.

**Acceptance criteria:**
- agents table has: provider (string, required), temperature, max_steps, max_tokens, timeout (nullable)
- agents table: tools and capabilities columns removed
- Agent model: fillable and casts updated; tools and capabilities removed
- Agent UI (create, edit, detail): provider field; tools/capabilities removed; optional overrides
- Feature test: create/edit agent with provider and overrides

**Priority:** 4  
**Status:** Done

---

### US-005 - Tool Registry

**As** a developer  
**I want** a tool registry that maps tool IDs to custom tools and provider tools  
**So that** runtime can resolve and filter tools by provider.

**Acceptance criteria:**
- ToolRegistry class maps tool IDs to Tool class or provider tool metadata
- Provider tools (e.g. WebSearch, WebFetch) include provider support list
- resolveTools(AgentType $type, string $provider) returns filtered tool list
- Unit test: registry resolves custom tools and filters provider tools by provider

**Priority:** 5  
**Status:** Done

---

### US-006 - Agent Runtime Resolution

**As** the system  
**I want** to resolve Agent + AgentType into SDK-ready config  
**So that** work execution can instantiate the correct agent.

**Acceptance criteria:**
- AgentResolver service (or similar) builds config: instructions, tools, model, provider, temperature, max_steps, max_tokens, timeout
- Resolution follows: type defaults, agent overrides; tools filtered by agent provider
- Unit test: resolver returns correct merged config

**Priority:** 6  
**Status:** Done

---

### US-007 - Agent UX Refinements and Tool Registry Alignment

**As** a platform administrator  
**I want** agent status to be system-managed (not user-editable) and the tool registry to align with Claude Code capabilities  
**So that** status reflects runtime state and tools match what Claude Code offers.

**Acceptance criteria:**
- Agent status removed from create and edit forms; new agents default to idle; status is read-only
- ToolRegistry includes Claude Code-aligned tools: bash, text_editor, code_execution (Anthropic), web_search, web_fetch, file_search

**Priority:** 7  
**Status:** Done

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
