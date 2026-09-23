# Cheat for arch-privacy-export-erase-integration

The straightforward implementation most people write first: four exporters and four erasers with
the specified groups, item IDs, anonymization rules and messages, the retention setting, cron job
and policy text. But:

- email addresses are compared exactly (`email = %s`), so the 1.x mixed-case newsletter rows and
  the guest orders typed as `JANE.DOE@example.com` are neither exported nor erased;
- the erasers page with `LIMIT 100 OFFSET (page-1)*100` like the exporters, although anonymized /
  deleted rows drop out of the match: for a member with 250 ledger entries 100 rows stay linked;
- the retention job is only scheduled in the activation hook, which never runs on sites that
  already have the plugin active.

Expected: export/erase/settings suites fail -> reward 0.
