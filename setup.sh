#!/bin/bash

set -e

echo "Setting up worktree..."

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Copy .env from main worktree if it doesn't exist
if [ ! -f .env ]; then
    MAIN_WORKTREE=$(git worktree list --porcelain | head -1 | sed 's/worktree //')
    if [ -f "$MAIN_WORKTREE/.env" ]; then
        cp "$MAIN_WORKTREE/.env" .env
        php artisan key:generate
        echo "Copied .env from main worktree (with new APP_KEY)"
    else
        cp .env.example .env
        php artisan key:generate
        echo "Created .env from .env.example (no main worktree .env found)"
    fi
fi

# Create SQLite database if it doesn't exist
if [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
fi

# Run migrations and seed
php artisan migrate --force
php artisan db:seed --force

# Build frontend assets
npm run build

echo "Setup complete."
