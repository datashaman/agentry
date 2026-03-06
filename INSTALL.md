# Installation

## Prerequisites

- PHP 8.4+
- Composer
- Node.js & npm
- SQLite (default) or another supported database

## Quick Setup

```bash
composer setup
```

This runs `composer install`, copies `.env.example` to `.env`, generates an app key, runs migrations, installs npm dependencies, and builds frontend assets.

## Manual Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
```

## Worktree Setup

For git worktree-based development, use the setup script which copies `.env` from the main worktree:

```bash
./setup.sh
```

## GitHub OAuth (Optional)

GitHub OAuth allows users to connect their GitHub accounts so agents can access repositories.

1. Go to **GitHub > Settings > Developer settings > OAuth Apps > New OAuth App**
2. Set the **Authorization callback URL** to `http://localhost:8000/auth/github/callback` (adjust for your domain)
3. After creating the app, copy the Client ID and generate a Client Secret
4. Add to your `.env`:

```
GITHUB_CLIENT_ID=your-client-id
GITHUB_CLIENT_SECRET=your-client-secret
GITHUB_REDIRECT_URI="${APP_URL}/auth/github/callback"
```

Users can then connect their GitHub account at **Settings > GitHub**.

## GitHub App (Optional)

The GitHub App enables repository browsing, webhook events, and deeper integration. To set up:

1. Create a GitHub App in your organization or account settings
2. Add to your `.env`:

```
GITHUB_APP_ID=your-app-id
GITHUB_APP_PRIVATE_KEY=your-private-key
GITHUB_WEBHOOK_SECRET=your-webhook-secret
```

After installation, organizations can browse and link repos from the project Repos tab.

## Jira Integration (Optional)

Jira OAuth allows projects to use Jira as their work item provider.

1. Go to **Atlassian Developer Console > Create OAuth 2.0 (3LO) App**
2. Set the **Callback URL** to `http://localhost:8000/auth/jira/callback` (adjust for your domain)
3. Add to your `.env`:

```
JIRA_CLIENT_ID=your-client-id
JIRA_CLIENT_SECRET=your-client-secret
JIRA_REDIRECT_URI="${APP_URL}/auth/jira/callback"
```

Users can then connect their Jira account at **Settings > Jira**. Projects can be configured to use Jira as their work item provider in project settings.

## Running the Application

```bash
composer run dev
```

## Running Tests

```bash
php artisan test
```

## Seeding

```bash
php artisan db:seed
```
