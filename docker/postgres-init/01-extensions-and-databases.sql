-- Runs once, on first container creation only (docker-entrypoint-initdb.d).
-- Creates the AIRS database alongside the default `indecs` database, and
-- enables the extensions both the core roadmap and the AI services layer
-- depend on: PostGIS for geometry, pgvector for semantic search, pg_trgm
-- for fuzzy name/lot matching.

CREATE DATABASE airs;

\connect indecs
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS vector;

\connect airs
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS vector;
