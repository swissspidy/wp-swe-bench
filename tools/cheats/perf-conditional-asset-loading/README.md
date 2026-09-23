# Cheat: detect components from the queried post's content only

Same asset split, handles, deferred loading and admin scoping as the reference, but the
components a page needs are detected in `wp_enqueue_scripts` with `has_block()` /
`has_shortcode()` on the queried post (the classic "conditional enqueue" snippet). Blocks in
synced patterns, template parts and sidebar widgets get no assets (unstyled, not interactive).

Expected: `phpunit_front` (synced pattern, template part), `e2e` (the same pages),
`phpunit_classic` and `e2e_classic` (widget) fail → reward 0.
