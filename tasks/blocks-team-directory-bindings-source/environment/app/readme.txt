=== Acme Team ===
Contributors: acmeweb
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.2.0
License: GPLv2 or later

Team member profiles for the Acme sites.

== Description ==

* "Team members" post type (`acme_member`, archive at `/team/`) with departments.
* Member details (meta box on the member screen): role, email, phone, photo (attachment ID), profile URL, and internal HR notes (never shown on the site).
* Shortcodes: `[team_member id="12" fields="role,email,phone"]` renders a card for one member; `[team_directory department="engineering"]` lists members.
* Only published members without a password are ever shown on the site.
* Themes can add fields with the `acme_team_fields` filter (e.g. pronouns), and read members with `acme_team_get_member( $id )`.

== Changelog ==

= 2.2.0 =
* Profile URL field.

= 2.1.0 =
* `acme_team_fields` filter, departments.

= 2.0.0 =
* Every field is stored in its own meta key (`_acme_role`, `_acme_email`, `_acme_phone`, `_acme_photo_id`). Members from 1.x keep working: their values are read from the old `acme_member_meta` array until they are saved again.
* Photo field.

= 1.0.0 =
* Initial release.
