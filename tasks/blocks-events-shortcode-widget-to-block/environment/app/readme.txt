=== Acme Events ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later

Event listings for the Acme sites.

== Description ==

* Post type "Event" (`acme_event`) with start/end date and venue ("When & where" box), and event categories (`acme_event_category`).
* Shortcode: `[acme_events limit="5" category="workshops,meetups" show_past="no" layout="list" title="" show_venue="yes"]`
  * `limit`: 1–20 (default 5)
  * `category`: comma separated event category slugs (default: all)
  * `show_past`: `yes` also lists past events, newest first (default `no`: upcoming only, soonest first)
  * `layout`: `list` or `grid` (default `list`)
  * `title`: optional heading above the listing
  * `show_venue`: `yes`/`no` (default `yes`)
* Widget: "Upcoming Events (Acme)" (title, number of events, category, show venue).
* Block: "Event details" (`acme/event-details`) for single event pages.
* WP-CLI: `wp acme-events list [--past] [--format=<format>]`.

== Theming ==

Listings are rendered from PHP templates in `templates/`. Themes can override them by copying them to
`{theme}/acme-events/`:

* `events.php` – wrapper (`div.acme-events.acme-events--{layout}`, optional `h2.acme-events__title`,
  `ul.acme-events__items` or `div.acme-events__grid`, or `p.acme-events__empty`)
* `event-list.php` – one event in the list layout (`li.acme-event`)
* `event-grid.php` – one event in the grid layout (`article.acme-event.acme-event--card`)

Filters:

* `acme_events_query_args( $query_args, $options )` – WP_Query arguments of a listing.
* `acme_events_item_html( $html, $event, $options )` – markup of one event.
* `acme_events_empty_message( $message, $options )` – text when nothing is found.
* `acme_events_date_format( $format, $event )` – date format.
* `acme_events_template( $path, $name )` – template path.

`$options` are the normalized listing options: `limit` (int), `category` (comma separated slugs),
`show_past` (bool), `layout` (`list`|`grid`), `title` (string), `show_venue` (bool).

== Changelog ==

= 2.3.0 =
* "Event details" block.
* `wp acme-events list` command.

= 2.2.0 =
* `title` and `show_venue` shortcode options.
* Theme template overrides.

= 2.1.0 =
* Grid layout.
* `acme_events_query_args` and `acme_events_item_html` filters.

= 2.0.0 =
* Events are a custom post type (were: posts in an "Events" category).

= 1.0.0 =
* Initial release.
