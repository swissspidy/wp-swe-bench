=== Acme Activity Log ===
Contributors: acme-web-team
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later

A searchable log of what happens on the site: logins, publishing, user and plugin changes.

== Description ==

* Tools → Activity Log: filter by action and user, search messages, export CSV, delete entries.
* Settings → Activity Log: choose which events are logged and whether IP addresses are stored.
* REST: `GET /wp-json/acme-activity/v1/entries` (administrators).
* Settings → Activity Log → "Keep entries for": entries older than that are deleted once a day (0 = keep forever).
* WP-CLI: `wp acme-activity list`, `wp acme-activity count`, `wp acme-activity migrate`, `wp acme-activity prune [--days=<n>]`.

= For developers =

* `acme_activity_log( $action, $args )` – log a custom event, returns the entry ID.
* `acme_activity_get_entries( $args )`, `acme_activity_count_entries( $args )`, `acme_activity_get_entry( $id )`, `acme_activity_delete_entries( $ids )`.
* `acme_activity_get_user_state( $user_id )`, `acme_activity_update_user_state( $user_id, $changes )` – per-user screen preferences.
* Filter `acme_activity_entry_data` (return false to skip an entry), action `acme_activity_logged`.

= Storage =

Entries are kept in the `{prefix}acme_activity_log` table (id, logged_at (UTC), user_id,
action, object_type, object_id, message, ip, context (JSON)). Per-user screen state is
stored in the `acme_activity_ui` user meta. Only the settings are autoloaded.

Before 3.0 the log lived in autoloaded options (`acme_activity_log`,
`acme_activity_log_archive_{n}`) and the screen state in `acme_activity_ui_state`
(1.x: `acme_activity_ui_{user_id}`). 3.0 moves them in the background, in batches;
`wp acme-activity migrate` finishes the move on demand.

== Changelog ==

= 3.0.0 =
* Performance: the log moved from autoloaded options into its own indexed table; screen state moved to user meta.
* Existing data is migrated in resumable background batches (`wp acme-activity migrate`).
* New "Keep entries for" retention setting with a daily cleanup and `wp acme-activity prune`.
* Uninstall removes the table, all options (including pre-3.0 leftovers), user meta and scheduled jobs.

= 2.3.1 =
* Fix: "(deleted user)" label for entries of deleted users.

= 2.3.0 =
* REST endpoint for the dashboard widget app.
* Plugin activation/deactivation events.

= 2.2.0 =
* Rows per page and hidden columns are remembered per user.
* "New since your last visit" highlighting.

= 2.1.1 =
* Fix: rotation in 2.1.0 could copy entries into both the archive and the current chunk. Duplicates are ignored when reading.

= 2.1.0 =
* Log rotation into archive chunks (the single option was getting too big to save).

= 2.0.0 =
* New compact entry format, object types, IP addresses, context data. 1.x entries are still read.
* Screen state moved into one option for all users.

= 1.4.0 =
* Rows per page setting per user.

= 1.0.0 =
* Initial release.
