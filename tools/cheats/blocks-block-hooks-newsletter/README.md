The "one-line" block hooks approach: `"blockHooks": { "core/post-content": "after" }`
in block.json, plus skipping the `the_content` filter in block themes.

What it misses: the form is inserted after *every* post-content block (Page template
too), placements are not configurable (settings ignored, legacy `auto_insert` ignored),
no footer placement, no `footer` source, manual block/shortcode posts get a duplicate,
the sponsored-post filter is ignored, and element IDs are still duplicated.
Expected: PHPUnit (front end, REST templates, settings) fails -> reward 0.
