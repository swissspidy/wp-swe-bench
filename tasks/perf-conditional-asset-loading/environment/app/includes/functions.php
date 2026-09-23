<?php
/**
 * Public API for themes and other plugins.
 *
 * @package Acme\UI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Make sure the UI Kit runtime and the given components are loaded on this page.
 *
 * Other plugins that print UI Kit markup themselves (data-acme-component="…") or register
 * their own components with AcmeUI.register() call this. It may be called any time before
 * the footer is printed.
 *
 * @param string[] $components Components the page needs ('tabs', 'accordion', 'carousel').
 *                             Empty: everything.
 */
function acme_ui_enqueue( $components = array() ) {
	Acme\UI\Plugin::instance()->assets->enqueue( (array) $components );
}

/**
 * Names of the UI Kit components.
 *
 * @return string[]
 */
function acme_ui_components() {
	return array( 'tabs', 'accordion', 'carousel' );
}
