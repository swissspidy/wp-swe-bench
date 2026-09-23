=== Acme Events Calendar ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Events for the Acme sites.

== Description ==

* Post type "Events" (`acme_event`) with a "Date & time" box in the editor (start, end, all-day, location).
* Single events show a details box (`.acme-event-details`) with `<time>` elements.
* Shortcode `[acme_upcoming_events limit="5" category="3,4"]`: events that have not ended yet, soonest first.
* iCalendar feed for calendar apps: `/feed/acme-events-ics/`.
* REST API for the mobile app: `GET /wp-json/acme-events/v1/events?upcoming=1&per_page=10`.

= For developers =

* `acme_events_save_event_dates( $post_id, $start, $end, $all_day )` saves the dates of an event (importers use it).
* `acme_events_get_upcoming( $args )` returns upcoming events.
* Filters: `acme_events_now` (the current time, a Unix timestamp; staging and QA use it to travel in time),
  `acme_events_upcoming_query_args`, `acme_events_details_html`, `acme_events_ical_vevent`, `acme_events_rest_item`.
* Action: `acme_events_dates_saved`.

== Changelog ==

= 2.0.0 =
* Every event now has its own timezone (the site's timezone when it was created) and stores its start/end
  in UTC (`_acme_event_timezone`, `_acme_event_start_utc`, `_acme_event_end_utc`). Existing events are
  converted automatically on update.
* Fix: events showed an hour off after daylight saving time changes; the upcoming list dropped events
  an hour early or late.
* Fix: all-day events were shown a day early on sites west of UTC, and exported a day early in the iCal
  feed on sites east of UTC.
* Fix: sites using a manual UTC offset (e.g. UTC+5:30) got UTC times in the REST API.
* Fix: 1.0 events were missing from the upcoming list.
* REST API: new `timezone` field.

= 1.6.2 =
* Fix: iCal feed lines longer than 75 octets are folded.

= 1.6.1 =
* Fix: all-day events in the REST API.

= 1.6.0 =
* Settings moved to the `acme_events_settings` option.
* Store start/end timestamps for faster queries.
* REST API for the mobile app.

= 1.5.0 =
* iCalendar feed.

= 1.4.0 =
* `acme_events_now` filter.

= 1.3.0 =
* Events store their start and end as date/time strings (was: timestamp + duration). Old events keep working.
* `acme_events_save_event_dates()`.

= 1.0.0 =
* Initial release.
