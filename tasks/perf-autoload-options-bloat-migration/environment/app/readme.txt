=== Acme Activity Log ===
Contributors: acme-web-team
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.1
License: GPLv2 or later

A searchable log of what happens on the site: logins, publishing, user and plugin changes.

== Description ==

* Tools → Activity Log: filter by action and user, search messages, export CSV, delete entries.
* Settings → Activity Log: choose which events are logged and whether IP addresses are stored.
* REST: `GET /wp-json/acme-activity/v1/entries` (administrators).
* WP-CLI: `wp acme-activity list`, `wp acme-activity count`.

= For developers =

* `acme_activity_log( $action, $args )` – log a custom event, returns the entry ID.
* `acme_activity_get_entries( $args )`, `acme_activity_count_entries( $args )`, `acme_activity_get_entry( $id )`, `acme_activity_delete_entries( $ids )`.
* `acme_activity_get_user_state( $user_id )`, `acme_activity_update_user_state( $user_id, $changes )` – per-user screen preferences.
* Filter `acme_activity_entry_data` (return false to skip an entry), action `acme_activity_logged`.

= Storage =

Entries are kept in the `acme_activity_log` option. Every 5,000 entries the option is
rotated into `acme_activity_log_archive_{n}`. Per-user screen state lives in
`acme_activity_ui_state` (1.x: `acme_activity_ui_{user_id}`).

== Changelog ==

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
