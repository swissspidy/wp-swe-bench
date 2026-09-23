=== Acme Events Lite ===
Contributors: acmedigital
Tags: events, calendar
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.0
License: GPLv2 or later

Lightweight events: an Event post type with dates, status and venue.

== Description ==

* **Events** post type (`acme_event`, archive at `/events/`, REST at `/wp/v2/events`).
* "Event details" panel in the editor: start, optional end, all-day, status, venue.
* `[acme_upcoming_events limit="5" show_venue="1"]` shortcode.
* **Upcoming events** variation of the Query Loop block (`acme/upcoming-events`):
  upcoming, non-cancelled events ordered by start, with pagination. The editor
  preview shows exactly what the front end shows.
* **Event date** block (`acme/event-date`) for use inside the loop (or on an event).
* Read-only `acme_event` REST field for the mobile app.

= Data model =

Event dates are stored as post meta:

* `_acme_event_start` (int): start, Unix timestamp (UTC). All-day events start at
  local midnight (site timezone).
* `_acme_event_end` (int, optional): end, Unix timestamp (UTC). Events created before
  1.3 have no end, and the 1.3 CSV importer stored `0` when the CSV had no end.
  **An event without an end lasts until the end of the day it starts on, in the
  site's timezone** (that's what the app and the admin list show).
* `_acme_event_all_day` (bool).
* `_acme_event_status` (string): `scheduled` (default when missing), `postponed` or
  `cancelled`. Events from 1.0 may still say `canceled`.
* `_acme_event_venue` (string).

`Acme\Events\Event` wraps these rules (effective end, normalized status); use it
instead of reading the meta directly.

= Time =

All "is it over yet?" decisions use `Acme\Events\now()`, which runs the
`acme_events_now` filter. Our staging sites use it to time-travel (e.g. to check
next month's listings), so never compare against `time()` directly.

= Hooks =

* `acme_events_now` (filter): current timestamp.
* `acme_events_upcoming_query_args` (filter): WP_Query args of the upcoming events list.

== Changelog ==

= 1.7.0 =
* New: "Upcoming events" Query Loop variation and "Event date" block.
* Fix: upcoming events use the site's timezone for "today", keep running multi-day events, and hide events cancelled in 1.0 ("canceled"). The shortcode uses the same rules and always returns up to `limit` events.

= 1.6.2 =
* Fix: admin list shows "(past)" for events without an end only after their day is over.

= 1.6.0 =
* New: `acme_event` REST field (`date_label`, ISO dates, status, venue) for the mobile app.

= 1.5.0 =
* New: "Event details" panel for the block editor (replaces the meta box).

= 1.4.0 =
* New: `postponed` status. The status is normalized to `cancelled` on save.

= 1.3.0 =
* New: optional end date. CSV importer.

= 1.0.0 =
* Initial release.
