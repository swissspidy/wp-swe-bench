<?php
/**
 * Block template parts (header / footer) for this classic theme.
 *
 * The header and footer live in parts/header.html and parts/footer.html so they
 * can be edited in Appearance → Editor → Patterns → Template Parts. header.php
 * and footer.php render them, so every PHP template keeps working.
 *
 * Navigation: the Navigation blocks in the parts point at the classic menu
 * locations (`__unstableLocation`). Until a site admin picks or builds a
 * navigation menu for them in the Site Editor (which stores a `ref`), they show
 * the menu assigned in Appearance → Menus, live. Without an assigned menu the
 * header lists the site's pages (like 3.x did) and the footer navigation is
 * omitted.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

/**
 * Enables the template part editor for this (classic) theme.
 */
function acme_corporate_block_template_parts_setup() {
	add_theme_support( 'block-template-parts' );
}
add_action( 'after_setup_theme', 'acme_corporate_block_template_parts_setup' );
