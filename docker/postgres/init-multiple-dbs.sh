#!/bin/bash
# Creates multiple PostgreSQL databases on first container startup.
# Referenced by docker-compose.yml: POSTGRES_MULTIPLE_DATABASES=hr_service,hub_service

set -e

function create_db() {
    local database=$1
    echo "Creating database '$database'..."
    psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" <<-EOSQL
        SELECT 'CREATE DATABASE $database' WHERE NOT EXISTS (
            SELECT FROM pg_database WHERE datname = '$database'
        )\gexec
EOSQL
}

if [ -n "$POSTGRES_MULTIPLE_DATABASES" ]; then
    echo "Multiple databases requested: $POSTGRES_MULTIPLE_DATABASES"
    for db in $(echo $POSTGRES_MULTIPLE_DATABASES | tr ',' ' '); do
        create_db $db
    done
    echo "Done creating databases."
fi
