# Cheat: self-closing fix + regex for url()

Applies the upstream 0.9.4 one-liner (keep the `/` of self-closing block comments when their attributes
are re-serialized), which fixes the menu and social icons, and bolts a `preg_replace_callback()` onto
`WP_Import::process_posts()` that rewrites **unquoted** `url(https://oldblog.example/…)` references in the
post content. That is enough for the cover blocks the core editor saves (`background-image:url(…)`),
but not for CSS as it appears in hand-written HTML: single/double-quoted values, `URL(` in upper case,
several `url()` in one declaration with entity-encoded quotes. It also works on the raw string instead
of the `style` attributes.

Expected: `phpunit_rewrite` (`test_css_urls_in_style_attributes`) fails → reward 0.
