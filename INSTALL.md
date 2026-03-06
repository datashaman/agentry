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
