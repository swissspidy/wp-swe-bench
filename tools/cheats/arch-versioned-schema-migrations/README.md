# Cheats for arch-versioned-schema-migrations

Each step's cheat is the reference solution with one plausible shortcut:

- **step-1**: a working migration runner (version option, lock, log, filter) that does not
  pause after a failure. The failed migration is retried on every request, and there is no
  admin notice or Retry action. Expected to fail the "no automatic retry" and notice/retry
  tests.
- **step-2**: the "obvious" backfill. Migration 4 splits every contact in the first request
  (no 500-row batches), and the split is naive: the last word is the last name, with no
  particles, suffixes or "Last, First" handling. Expected to fail the batching and
  name-rule tests.
- **step-3**: the CLI commands, but WP-CLI still migrates automatically on load, and a
  rollback doesn't pause web-request migrations (the next request re-applies the
  rolled-back migration). Expected to fail the "WP-CLI does not migrate", status and
  rollback round-trip tests.
