=== Acme Social ===
Contributors: acmeweb
Tags: share buttons, open graph, social
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.6.2
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

= Settings used by other plugins =

Some of our other plugins and themes read settings directly with `get_option()`:
Acme Newsletter reads `acme_social_twitter` and `acme_social_facebook` for its email footer,
the Acme Theme reads `acme_social_networks` for its sticky share bar and `acme_og_enabled`
to avoid printing duplicate Open Graph tags.

== Changelog ==

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
