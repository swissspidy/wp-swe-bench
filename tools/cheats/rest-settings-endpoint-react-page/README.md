The "obvious" 2.0: one `acme_seo_settings` setting with the nested schema on
`/wp/v2/settings`, partial updates, the React settings page and no admin-post handler
(identical to the reference solution), but the upgrade routine copies the raw old option
values with simple casts instead of interpreting them the way 1.9.2 did, runs on
`plugins_loaded` (before the Events post type is registered), and there is no `get_option()`
back-compat for the three option names Acme Social Share reads.

Expected: MigrationTest (seeded verification meta tag, entities, URLs, 1.0 formats, Events),
CompatTest (old option names, share bar) fail -> reward 0.
