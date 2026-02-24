-- Runs automatically on first-time PostgreSQL data directory initialization.
-- Creates both service databases if they do not already exist.

SELECT 'CREATE DATABASE hr_service'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'hr_service')\gexec

SELECT 'CREATE DATABASE hub_service'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'hub_service')\gexec
