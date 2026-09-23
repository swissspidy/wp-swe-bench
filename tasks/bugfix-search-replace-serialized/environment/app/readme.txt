=== Acme Migrate ===
Contributors: acmeagency
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.4.2
License: GPLv2 or later

Site migration helpers used by the Acme agency when moving client sites between hosts and domains.

== Description ==

* **Tools → Acme Migrate**: search & replace across the database (e.g. the old site URL), with a dry run
  option, a report per table/column and the list of recent runs.
* **WP-CLI**: `wp acme-migrate search-replace <search> <replace> [--tables=<tables>] [--dry-run] [--format=<table|json|count>]`
  and `wp acme-migrate history`.
  * `--format=table` (default) prints the report table followed by the summary line
    (`Success: Made 12 replacements in 7 rows.` or, for a dry run,
    `Success: 12 replacements in 7 rows would be made (dry run).`).
  * `--format=json` prints the report as a JSON list of `{"table", "column", "rows", "replacements"}`
    objects (only columns with changes).
  * `--format=count` prints the number of replacements.
* Walks the core tables and every table added through the `acme_migrate_tables` filter (see
  `includes/class-table-map.php`). The plugin's own options are never rewritten.
* Public functions for the Pro add-on and deploy scripts: `acme_migrate_replace()`, `acme_migrate_run()`,
  `acme_migrate_get_tables()`, `acme_migrate_summary()`; action `acme_migrate_after_run`.

A "replacement" is one occurrence of the search string that was replaced; "rows" counts changed values
(a row changed in two columns counts twice).

== Changelog ==

= 1.4.2 =
* Values are escaped before they are written to the database.

= 1.4.0 =
* Run history (`wp acme-migrate history`, "Recent runs" on the admin screen).
* Batched queries for large tables.

= 1.3.0 =
* Admin screen shows the report per table/column.
* `--format=count`.

= 1.2.0 =
* Serialized values are unserialized and serialized again so that string lengths stay correct.
* `acme_migrate_replace()` reports the number of replacements.

= 1.1.0 =
* `acme_migrate_tables` filter for plugin tables. `acme_migrate_after_run` action.

= 1.0.0 =
* Initial release (WP-CLI only).
