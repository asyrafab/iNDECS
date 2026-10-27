-- Runs automatically on first container creation; safe to rerun on existing volumes.
-- Creates the AIRS database alongside the default `indecs` database, and
-- enables the extensions both the core roadmap and the AI services layer
-- depend on: PostGIS for geometry, pgvector for semantic search, pg_trgm
-- for fuzzy name/lot matching.

\set ON_ERROR_STOP on

SELECT 'CREATE DATABASE airs'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'airs')
\gexec

SELECT 'CREATE DATABASE indecs_test'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'indecs_test')
\gexec

\connect indecs
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS vector;

\connect airs
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS vector;

\connect indecs_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS vector;
