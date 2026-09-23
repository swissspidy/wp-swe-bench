# Cheat: migration that ignores writes happening during the migration

Everything from the reference solution (table, batched resumable/idempotent migration,
user meta, retention, CLI, uninstall) except one detail: the upgrade does not make sure
that entries logged *while the background migration is still running* get IDs above the
legacy ID range. With an auto-increment table that only contains the first migrated
batches, a new event gets the ID of a legacy entry that has not been copied yet; the
idempotent "skip IDs that already exist" logic then silently drops that legacy entry.

This is the most plausible subtle bug in an otherwise complete solution. Expected:
`MigrationTest::test_b_…` fails ("An entry logged during the migration got the ID of an
existing entry" / legacy entries missing) and the dependent parity tests are skipped → reward 0.
