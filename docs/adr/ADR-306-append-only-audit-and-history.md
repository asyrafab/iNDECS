# ADR-306: Append-only audit and stage-history immutability

Date: 2026-10-27

## Status

Accepted.

## Context

The repository readiness review (document 18) and programme issue `PD-224`
identified three weaknesses in the audit and workflow history tables
(`audit_logs` and `stage_histories`):

1. Both tables are ordinary writable PostgreSQL tables; `AuditLog::UPDATED_AT = null`
   only suppresses Eloquent's `updated_at` column.
2. `stage_histories.application_id` uses `ON DELETE CASCADE`, allowing
   stage-history rows to be removed if an application row is deleted.
3. `stage_histories.actor_id` and `audit_logs.actor_id` use `ON DELETE SET NULL`,
   allowing historical actor attribution to be erased if a user row is deleted.

## Decision

1. Replace the foreign keys on `stage_histories.application_id`,
   `stage_histories.actor_id` and `audit_logs.actor_id` with `ON DELETE RESTRICT`
   so neither applications with history nor users referenced as historical actors
   can be deleted.
2. Create a PostgreSQL trigger function `core.prevent_history_mutation()` that
   raises SQLSTATE `42501` (`insufficient_privilege`) on any `UPDATE` or `DELETE`
   against `audit_logs` or `stage_histories`. Attach `BEFORE UPDATE OR DELETE`
   row triggers to both tables so immutability holds even when connected as the
   table owner in local development and testing.
3. Guard `updating` and `deleting` model events on `App\Models\AuditLog` and
   `App\Models\StageHistory` to fail fast in application code as well.
4. Keep the migration reversible (`down()` drops the triggers and function and
   restores the prior foreign key definitions) so `scripts/verify-database.php`
   can rehearse `migrate:fresh`, `migrate:rollback` and `migrate`.

## Consequences

- `INSERT` remains the only permitted row operation on `audit_logs` and
  `stage_histories`, whether issued through Eloquent or raw SQL.
- Deleting an application or a user referenced by history or audit rows is
  blocked at the database boundary.
- Test database rebuilds (`migrate:fresh`) continue to work because `DROP TABLE`
  is DDL rather than `DELETE`.
