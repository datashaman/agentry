# Agentry

An AI-powered software engineering platform where autonomous agents collaborate to plan, build, review, test, and deploy code. Agents are organized into teams with configurable workflows (chain, parallel, evaluator-optimizer) and respond to work item status changes via event responders.

## Features

- **Agent orchestration** — Define agent roles (planning, coding, review, testing, ops) with skills and assign them to teams
- **Team workflows** — Chain, parallel, router, orchestrator, and evaluator-optimizer patterns
- **Event responders** — Agents automatically react to work item status transitions
- **Project management** — Epics, stories, bugs, milestones, labels, and ops requests
- **Code integration** — Repos, branches, worktrees, pull requests, and change sets
- **Human-in-the-loop** — Escalation system for agent decisions that need human review
- **GitHub OAuth** — Connect GitHub accounts for repository access
- **Multi-org** — Organization-based tenancy with team membership

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

## License

Proprietary.
