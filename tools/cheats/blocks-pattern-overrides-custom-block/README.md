Shortcut: register `heading`, `buttonText` and `buttonUrl` as bindable attributes and give them
HTML sources (`h2,h3,h4`, `a`, `a[href]`) so core's static HTML replacement applies the overrides.
The editor side works, but: the existing tracking `render_block` filter still rewrites the link
from the *parsed* (pattern) attributes, so overridden links are replaced by the pattern's link;
override values are only filtered with `wp_kses_post` (an `<img onerror>` survives, `javascript:` /
`data:` links are output verbatim); newly saved CTAs no longer store their values in the block
comment, so the CTA inventory loses them. Expected: PHPUnit + E2E failures → reward 0.
