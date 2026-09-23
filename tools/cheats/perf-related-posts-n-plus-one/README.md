# Cheat: time-based caching without invalidation

Same batching and priming as the reference solution (so the query budgets are met once
the cache is warm), but the computed related lists are stored in one-hour transients and
an in-memory memo that is never invalidated. Editor-pick changes, new/retagged/trashed
posts and flag changes only show up after the cache expires.

Expected: `phpunit_invalidation` (in-process freshness) and `phpunit_http_invalidation`
(freshness across requests) fail; cold archive pages also exceed the budget because every
transient is read separately. Reward 0.
