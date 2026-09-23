# Cheat: index maintained on save only

Complete reference solution (lookup tables, index-based search with identical ordering,
cache priming, automatic backfill, `wp acme-listings reindex`) except that the index is
refreshed only from `save_post_acme_listing` / `deleted_post`, which is what most people
reach for first. That covers the edit screen and `wp_insert_post( … 'meta_input' … )`,
but not importers or integrations that call `update_post_meta()` / `add_post_meta()` /
`delete_post_meta()` after the post was saved (the plugin's own CSV importer writes meta
after `wp_insert_post()`/`wp_update_post()`), nor `wp post meta update`.

Expected: `SyncTest` (price/bedrooms/features/city/status updates, importer-style
listing creation, random changes vs. the reference model) and
`CliHttpTest::test_cli_import_updates_the_search` / `test_wp_cli_meta_commands_…` fail → reward 0.
