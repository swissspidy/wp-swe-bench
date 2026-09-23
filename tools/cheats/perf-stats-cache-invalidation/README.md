# Cheats for perf-stats-cache-invalidation

## step-1: time-based expiry only

Each scope's numbers are stored in a transient that expires after 10 minutes; nothing
invalidates it. Repeat loads are cheap and per-user scopes don't leak, but the numbers are
wrong for up to 10 minutes after publishing, commenting, re-categorising etc.
Expected: `phpunit_invalidation` and `phpunit_http` fail → reward 0 (and the trial stops).

## step-2: lock that only works with a persistent object cache

Starts from the full 1.7.0 reference (stale-while-revalidate, WP-CLI, flush), but takes the
regeneration lock with `wp_cache_add()` only. Without a persistent object cache the lock is
per-request memory, so every concurrent request regenerates (and waits) instead of getting
the previous numbers. Expected: `phpunit_stampede` (no persistent cache) fails; the
`_objcache` run passes → reward 0 for step 2.
