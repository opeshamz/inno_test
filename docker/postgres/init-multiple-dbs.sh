#!/bin/bash
# PostgreSQL docker-entrypoint-initdb.d script.
# Runs ONCE on first boot (empty data directory).
# Creates hr_service and hub_service with the correct owner and password.
set -e

echo ">>> [init] Creating application databases..."

psql -v ON_ERROR_STOP=1 \
     --username "$POSTGRES_USER" \
     --dbname   "postgres" \
     <<-'EOSQL'

-- hr_service database
SELECT 'CREATE DATABASE hr_service OWNER postgres'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'hr_service'
)\gexec

-- hub_service database
SELECT 'CREATE DATABASE hub_service OWNER postgres'
WHERE NOT EXISTS (
    SELECT FROM pg_database WHERE datname = 'hub_service'
)\gexec

-- Confirm
SELECT datname FROM pg_database WHERE datname IN ('hr_service', 'hub_service');

EOSQL

echo ">>> [init] Done — hr_service and hub_service are ready."
