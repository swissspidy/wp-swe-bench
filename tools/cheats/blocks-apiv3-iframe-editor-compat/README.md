Complete editor fix (API v3, ref-based drawing, ResizeObserver, canvas styles, React legend
toggles), but the "only load scripts where charts appear" requirement is done the common
shortcut way: `wp_enqueue_scripts` + `is_singular() && has_block()` on the queried post.
Charts inside synced patterns (`core/block` refs) are not detected, so the chart in the
"Revenue report" post is never drawn and its scripts are missing (PHPUnit synced-pattern
test + front-end e2e test fail) → reward 0.
