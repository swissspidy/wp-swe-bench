=== Acme SEO ===
Contributors: acme
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 2.0.0
License: GPL-2.0-or-later

Titles, meta descriptions, robots rules, Open Graph/Twitter tags, XML sitemap tweaks and search engine verification for Acme sites.

== Description ==

Settings live under **Settings → SEO**. Posts get an "SEO" panel in the block editor
(custom title, meta description, "hide from search engines").

= For developers =

Read settings with `acme_seo_get_option( $key, $default = null )`. Keys:

* `title_separator` – one of `-`, `–`, `|`, `·`, `»`
* `home_title` – template with `%%sitename%%`, `%%tagline%%`, `%%sep%%`
* `home_description`
* `noindex_post_types` – array of post type slugs
* `noindex_archives` – `array( 'author' => bool, 'date' => bool, 'tag' => bool )`
* `og_enabled` – bool
* `og_default_image` – attachment ID (0 = none)
* `twitter_handle` – without "@"
* `social_profiles` – `array( 'facebook' => url, 'instagram' => url, 'linkedin' => url, 'youtube' => url )`
* `sitemap_enabled` – bool
* `sitemap_exclude` – array of post IDs
* `verification` – `array( 'google' => code, 'bing' => code )`

Since 2.0 all settings are stored in one option, `acme_seo_settings`, which is also
available to REST clients as `acme_seo_settings` on `/wp/v2/settings` (administrators only):

    {
      "titles":       { "separator", "home_title", "home_description" },
      "indexing":     { "noindex_post_types", "noindex_author_archives", "noindex_date_archives", "noindex_tag_archives" },
      "social":       { "og_enabled", "default_image", "twitter_handle", "profiles": { "facebook", "instagram", "linkedin", "youtube" } },
      "sitemap":      { "enabled", "exclude" },
      "verification": { "google", "bing" }
    }

Updates may contain only the parts that change. The twelve 1.x `acme_seo_*` options are
migrated on upgrade and deleted. For older integrations, `get_option()` still answers for
`acme_seo_twitter_handle` ("@handle"), `acme_seo_og_default_image` (attachment ID) and
`acme_seo_social_profiles`; please switch to `acme_seo_get_option()`.

Hooks: `acme_seo_description`, `acme_seo_open_graph_tags`,
`acme_seo_settings_saved`, `acme_seo_upgraded`, `acme_seo_loaded`.

`acme_seo_title_template( $template )` renders a title template.

== Changelog ==

= 2.0.0 =
* New Settings → SEO screen (React), saving through the REST API with validation messages.
* All settings are stored in the `acme_seo_settings` option and exposed on `/wp/v2/settings`.
* The old options are migrated and removed on upgrade.
* Only administrators can manage the SEO settings; the `acme_seo_settings_capability` filter was removed.

= 1.9.2 =
* Fix: date archives were indexed when the archive settings had never been saved.

= 1.9.0 =
* Block editor panel for per-post SEO fields (replaces the meta box).

= 1.8.0 =
* The three "noindex archive" options were merged into `acme_seo_noindex_archives`.

= 1.6.0 =
* `acme_seo_twitter` renamed to `acme_seo_twitter_handle`.

= 1.5.0 =
* The default share image is stored as an attachment ID instead of a URL.

= 1.2.0 =
* Open Graph tags. Settings use "yes"/"no" instead of "1"/"0".

= 1.0.0 =
* First release.
