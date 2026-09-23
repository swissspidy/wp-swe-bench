# Cheat: slashing-only fix

Fixes all the backslash corruption (plain string meta, nested arrays, objects, post content on
Clone/New Draft and on Rewrite & Republish) — the part of the report that is easy to reproduce
with a regular post — but keeps the taxonomy map that is built on `init` (priority 10) for the
enabled post types. Release notes (post type registered on `init` priority 20) still lose their
Channel/Component terms on Clone, New Draft, bulk Clone and Rewrite & Republish.

Expected: `phpunit_clone` and `phpunit_rewrite` fail on the release-notes tests → reward 0.
