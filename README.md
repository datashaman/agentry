# Agentry

An AI-powered software engineering platform where autonomous agents collaborate to plan, build, review, test, and deploy code. Agents are organized into teams with configurable workflows (chain, parallel, evaluator-optimizer) and respond to work item status changes via event responders.

## Features

- **Agent orchestration** — Define agent roles (planning, coding, review, testing, ops) with skills and assign them to teams
- **Team workflows** — Chain, parallel, router, orchestrator, and evaluator-optimizer patterns
- **Event responders** — Agents automatically react to work item status transitions
- **External work items** — Jira and GitHub Issues integration for stories, bugs, and tasks
- **Code integration** — Link GitHub repos via GitHub App installation
- **Human-in-the-loop** — Escalation system for agent decisions that need human review
- **Agent permissions** — Organization-level controls for what agents can do (branches, PRs, code, deployments, etc.)
- **Skills system** — Reusable agent capabilities, importable from repos with version tracking
- **GitHub OAuth & App** — Connect GitHub accounts and install the GitHub App for repository access
- **Jira OAuth** — Connect Jira Cloud for work item integration
- **Multi-org** — Organization-based tenancy with team membership and agent permissions

## Tech Stack

- **Backend:** Laravel 12, PHP 8.4+, Livewire 4
- **Frontend:** Flux UI, Tailwind CSS 4, Alpine.js
- **Auth:** Laravel Fortify (2FA support)
- **Testing:** Pest 4
- **Queue:** Database (configurable)

## Getting Started

See [INSTALL.md](INSTALL.md) for setup instructions.

### Quick Start

```bash
composer setup
composer run dev
```

### Running Tests

```bash
php artisan test
```

## Development

```bash
composer run dev    # Starts server, queue worker, log tail, and Vite
composer run lint   # Run Pint code formatter
```

## Architecture

- [Object Model](docs/architecture/object-model.md) — Domain model and entity relationships
- [Agent System & SDK Parity](docs/architecture/agents-and-sdk-parity.md) — AgentRole/Agent split and Laravel AI SDK alignment
- [Workflows](docs/architecture/workflows.md) — OpsRequest lifecycle and event-driven agent work

## License

Proprietary.
