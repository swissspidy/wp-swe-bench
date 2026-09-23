=== Acme Social ===
Contributors: acmeweb
Tags: share buttons, open graph, social
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPL-2.0-or-later

Share buttons, social profile links and Open Graph / Twitter card tags for Acme sites.

== Description ==

* Share buttons before/after the content of the post types you choose (Acme Social → Sharing).
* `[acme_share]` shortcode to place the share buttons anywhere.
* `[acme_social_profiles]` shortcode listing the site's social profiles (Acme Social → Profiles).
* Open Graph and Twitter card tags in the document head (Acme Social → Open Graph).
* Per-post "Hide share buttons" switch.

= Filters =

* `acme_social_networks` – add/remove share networks (`slug => [ label, share_url ]`).
* `acme_social_display_share_buttons` – whether buttons are shown for a post.
* `acme_social_share_url` – the URL that is shared (buttons and `og:url`).
* `acme_social_meta_tags` – the list of social meta tags before output.
* `acme_social_profile_links` – the profile links.
* `acme_social_admin_capability` – capability needed to manage the settings (default `manage_options`).

= Settings =

Since 2.0 all settings are stored in the `acme_social_settings` option (an array, see
`Acme_Social_Settings::defaults()`). Use the `acme_social_*()` accessor functions to read them.

The old option names `acme_social_twitter` ("@handle"), `acme_social_facebook` (URL),
`acme_social_networks` (comma-separated slugs) and `acme_og_enabled` ("1" or "") can still be
read with `get_option()` (Acme Newsletter, Acme Theme) and always return the current settings.
They are deprecated and will be removed in 3.0.

== Changelog ==

= 2.0.0 =
* Change: all settings are stored in one `acme_social_settings` option; existing settings are
  migrated automatically on the first request after the update and the old options are removed.
* Change: the three settings screens use the standard WordPress settings flow, validate input
  and show errors for values that were not saved.
* Security: the Profiles screen could be saved by any logged-in user without verification.
* New: uninstalling the plugin removes its settings, cached tags and post meta.

= 1.6.2 =
* Fix: Open Graph cache was not cleared when the default share image changed.

= 1.6.0 =
* New: Mastodon can be added through the `acme_social_networks` filter.
* New: Twitter card type setting.

= 1.5.0 =
* New: YouTube channel profile.
* Change: moved the Profiles save handler (notices were lost after the menu refactor).

= 1.4.0 =
* New: `acme_social_admin_capability` and `acme_social_meta_tags` filters.
* New: button style "Icons and text" (stored as "both" in 1.4.0-beta, "icons+text" in 1.3).

= 1.3.0 =
* New: button styles.
* New: `acme_social_display_share_buttons` filter.

= 1.2.0 =
* Networks stored as a comma separated list.
* Default share image picker (stored the image URL).

= 1.1.0 =
* Twitter card support ("large" cards).
* `[acme_social_profiles]` shortcode.

= 1.0.0 =
* Initial release.
