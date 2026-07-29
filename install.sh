#!/usr/bin/env bash

set -e

echo "====================================================="
echo "        FORT (Fast Short) - Interactive Installer    "
echo "====================================================="
echo ""

# Clean install flag check
if [[ "$1" == "--clean" ]]; then
    echo "⚠️  CLEAN INSTALL REQUESTED ⚠️"
    echo "This will delete the existing .env, SQLite database, logs, cache, and vendor directory."
    read -p "Are you sure you want to proceed? [y/N]: " CLEAN_CONFIRM
    if [[ "$CLEAN_CONFIRM" =~ ^[Yy]$ ]]; then
        echo "Cleaning up previous installation..."
        rm -f .env
        rm -f storage/fort.sqlite
        rm -rf storage/logs/*
        rm -rf storage/cache/*
        rm -rf vendor/
        echo "✅ Cleanup complete."
        echo ""
    else
        echo "Clean install aborted."
        exit 1
    fi
fi

# 1. Check requirements
if ! command -v php >/dev/null 2>&1; then
    echo "❌ Error: PHP is not installed. Please install PHP 8.2+ first."
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
echo "✅ PHP Version: $PHP_VERSION"

if ! command -v composer >/dev/null 2>&1; then
    echo "❌ Error: Composer is not installed. Please install Composer first."
    exit 1
fi
echo "✅ Composer installed"

echo ""
echo "--- 1. Configuration ---"

# App Name
read -p "Enter App Name [FORT (Fast Short)]: " APP_NAME
APP_NAME=${APP_NAME:-"FORT (Fast Short)"}

# App URL
read -p "Enter App URL (e.g., https://fort.domain.com): " APP_URL
if [ -z "$APP_URL" ]; then
    echo "❌ Error: App URL is required."
    exit 1
fi
# Remove trailing slash if present
APP_URL=${APP_URL%/}

# Database Driver
echo ""
echo "Select Database Driver:"
echo "1) SQLite (Default, easiest & zero-config)"
echo "2) PostgreSQL"
read -p "Enter choice [1]: " DB_CHOICE

DB_DRIVER="sqlite"
if [ "$DB_CHOICE" == "2" ]; then
    DB_DRIVER="pgsql"
    read -p "DB Host [127.0.0.1]: " DB_HOST
    DB_HOST=${DB_HOST:-127.0.0.1}
    read -p "DB Port [5432]: " DB_PORT
    DB_PORT=${DB_PORT:-5432}
    read -p "DB Name [fort]: " DB_DATABASE
    DB_DATABASE=${DB_DATABASE:-fort}
    read -p "DB User [postgres]: " DB_USERNAME
    DB_USERNAME=${DB_USERNAME:-postgres}
    read -sp "DB Password: " DB_PASSWORD
    echo ""
fi

# Environment setup
echo ""
echo "--- 2. Setting up environment ---"
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo "✅ Created .env file from .env.example"
    else
        touch .env
        echo "✅ Created new .env file"
    fi
else
    echo "⚠️ .env file already exists. Updating values..."
fi

# Function to safely update or append to .env
update_env() {
    local key=$1
    local value=$2
    # Escape special characters for sed
    local escaped_value=$(echo "$value" | sed -e 's/[\/&]/\\&/g')
    
    if grep -q "^${key}=" .env; then
        sed -i "s/^${key}=.*/${key}=\"${escaped_value}\"/" .env
    elif grep -q "^# ${key}=" .env; then
        sed -i "s/^# ${key}=.*/${key}=\"${escaped_value}\"/" .env
    else
        echo "${key}=\"${value}\"" >> .env
    fi
}

update_env "APP_NAME" "$APP_NAME"
update_env "APP_URL" "$APP_URL"
update_env "APP_ENV" "production"
update_env "DB_DRIVER" "$DB_DRIVER"

if [ "$DB_DRIVER" == "pgsql" ]; then
    update_env "DB_HOST" "$DB_HOST"
    update_env "DB_PORT" "$DB_PORT"
    update_env "DB_DATABASE" "$DB_DATABASE"
    update_env "DB_USERNAME" "$DB_USERNAME"
    update_env "DB_PASSWORD" "$DB_PASSWORD"
fi

# App Key generation
if grep -q "^APP_KEY=\"\"" .env || ! grep -q "^APP_KEY=" .env; then
    APP_KEY=$(php -r "echo bin2hex(random_bytes(32));")
    update_env "APP_KEY" "$APP_KEY"
    echo "✅ Generated secure APP_KEY"
fi

echo ""
echo "--- 3. Installing Dependencies ---"
composer install --no-dev --optimize-autoloader

echo ""
echo "--- 4. Setting Permissions ---"
mkdir -p storage/logs storage/cache
chmod -R 775 storage
echo "✅ Set permissions 775 on storage/ directory"

echo ""
echo "--- 5. Database Migration ---"
php database/migrate.php
echo "✅ Database migrated"

echo ""
echo "====================================================="
echo "🎉 FORT Installation Complete!"
echo "====================================================="
echo ""
echo "Next steps:"
echo "1. If your webserver runs under a different user (e.g., www-data), make sure to change ownership:"
echo "   sudo chown -R www-data:www-data storage/"
echo "2. Add this cronjob to clean up expired links automatically:"
echo "   * * * * * php $(pwd)/cron/cleanup.php >> /dev/null 2>&1"
echo "3. Open your browser and navigate to:"
echo "   $APP_URL/install"
echo ""
