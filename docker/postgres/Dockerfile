# PostgreSQL 16 with PostGIS (base image) plus pgvector layered on top via
# the PGDG apt repository the postgis image already configures.
#
# docs/bangunan/11-indecs-core-roadmap.md 2.1: "PostGIS (lot and footprint
# geometry), pgvector (semantic search for the internal AI layer), pg_trgm
# (fuzzy name and lot matching)".
FROM postgis/postgis:16-3.4

RUN apt-get update \
    && apt-get install -y --no-install-recommends postgresql-16-pgvector \
    && rm -rf /var/lib/apt/lists/*
