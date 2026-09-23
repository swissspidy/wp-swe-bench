Complete-looking 2.0: the format, term search, shortcode conversion and the new
accessible markup all work. But the renderer reuses the plugin's existing cached
lookup map (the `acme_glossary_map` transient, "one query per page") for the
definition and for "is this term published", and the cache invalidation is left
as it was (only on `save_post`). Definitions changed through meta/WP-CLI and
force-deleted terms keep being shown.

Expected: the "changed definitions" and "deleted terms" PHPUnit tests fail → reward 0.
