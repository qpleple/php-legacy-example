#!/bin/bash
set -e

DB_PATH="${SQLITE_DB:-/var/www/html/data/compta.db}"
SQL_DIR="/var/www/sql"

# Ensure data directory exists and is writable
mkdir -p "$(dirname "$DB_PATH")"
chown -R www-data:www-data "$(dirname "$DB_PATH")"

# Initialize database if it doesn't exist
if [ ! -f "$DB_PATH" ]; then
    echo "Initializing SQLite database..."

    if [ -f "$SQL_DIR/01_schema.sql" ]; then
        sqlite3 "$DB_PATH" < "$SQL_DIR/01_schema.sql"
        echo "Schema loaded."
    fi

    if [ -f "$SQL_DIR/02_seed.sql" ]; then
        sqlite3 "$DB_PATH" < "$SQL_DIR/02_seed.sql"
        echo "Seed data loaded."
    fi

    if [ -f "$SQL_DIR/03_triggers.sql" ]; then
        sqlite3 "$DB_PATH" < "$SQL_DIR/03_triggers.sql"
        # No echo - triggers are "hidden"
    fi

    chown www-data:www-data "$DB_PATH"
    echo "Database initialized at $DB_PATH"
fi

exec "$@"
