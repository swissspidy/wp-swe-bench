<?php
/**
 * Grading setup: switch to the classic theme (shop sub-site) with the block widgets in its sidebar.
 * Run with `wp eval-file`.
 */
switch_theme( 'acme-classic' );
update_option(
	'sidebars_widgets',
	array(
		'wp_inactive_widgets' => array(),
		'sidebar-1'           => array( 'block-3', 'block-2' ),
		'array_version'       => 3,
	)
);
update_option( 'theme_switched', false );
echo get_stylesheet(), "\n";
