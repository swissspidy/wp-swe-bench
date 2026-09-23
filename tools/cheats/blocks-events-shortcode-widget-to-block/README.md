Cheat for step 2 (step 1 uses the reference solution).

The "quick" retirement: the widget is unregistered and a string-replace migration is added.

- `wp acme-events migrate-shortcodes` regex-replaces `<!-- wp:shortcode -->[acme_events …]<!-- /wp:shortcode -->`
  with a block comment whose attributes are the raw shortcode attributes (`{"limit":"3","show_past":"yes",…}`):
  no mapping to `showPast`/`showVenue`, no integer/boolean types, only one shortcode per Shortcode block, and
  the dry-run flag is honoured but the counts include posts it can't convert.
- Widgets are converted on `admin_init` (only when someone opens wp-admin), keep the category term ID
  instead of the slug and always write to `widget_block` index 2+count (overwriting existing block widgets).

Expected: step-2 PHPUnit (widget conversion, CLI counts/parity/types) and E2E fail → step-2 reward 0.
