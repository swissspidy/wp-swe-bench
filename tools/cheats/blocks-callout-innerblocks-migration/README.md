Editor-only upgrade: InnerBlocks, deprecations with migrations and the shortcode
transform, but the new markup is saved statically. Existing posts render the old
markup until re-saved, shortcodes still render 1.x markup, and there is no
aria-label. Expected: PHPUnit front-end tests fail → reward 0.
