Shortcut: the complete `wp acme-redirects` command suite (same output formats, exit codes,
validation, streaming import/export, `test` through the extracted matcher), but add/update/delete
and the importer write rows with direct `$wpdb` queries "for speed" instead of going through
`Rule_Repository`. The cached front-end rule set is not invalidated and the
`acme_redirects_rules_changed` hook (CDN purge) never fires, so changes made from the CLI
don't take effect on the front end until the cache expires. Expected: CRUD/import tests that
check the front end and the purge log fail -> reward 0.
