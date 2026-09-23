=== Acme Bookings ===
Contributors: acmehospitality
Tags: bookings, rooms, reservations
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later

Room bookings for Acme guest houses.

== Description ==

* Rooms are a custom post type (`acme_room`) with a capacity and a nightly rate.
* Bookings live in the `{prefix}acme_bookings` table (all dates UTC).
* The office manages bookings under **Bookings** (users with the `manage_acme_bookings`
  capability: administrators and the Booking Manager role).
* `[acme_availability room="123"]` shows the booked dates of a room; logged-in guests can
  request a booking from it.
* The office gets an e-mail for every new booking request; customers get an e-mail when
  their booking is confirmed or cancelled.

= Developer notes =

Actions: `acme_bookings_booking_created( $id, $row )`, `acme_bookings_status_changed( $id, $new, $old )`.
Filters: `acme_bookings_manager_capability`, `acme_bookings_currency`, `acme_bookings_quote`.

== Changelog ==

= 2.0.0 =
* REST API: `acme-bookings/v1/bookings` is now a documented, schema-driven API (validation errors,
  contexts, pagination headers, `_links`/`_embed`, overlap detection with 409 responses).
* Security: bookings are no longer visible to logged-out visitors; customers only see and change
  their own bookings.
* Office screen and availability widget updated for the new API.

= 1.6.0 =
* Availability widget: request a booking without leaving the page.
* Office screen: pagination.

= 1.5.0 =
* Office screen rewritten in plain JavaScript on top of the REST routes (no more admin-ajax).
* Currency setting.

= 1.4.0 =
* Internal notes (`admin_notes`) for the office.
* Status values are now `pending`, `confirmed` and `cancelled` (older bookings keep `approved`/`canceled` in the table).

= 1.2.0 =
* Store the total price with each booking (older bookings are priced with the room's current rate).

= 1.0.0 =
* First release.
