<?php
/**
 * Acme Corporate functions and definitions.
 *
 * @package Acme_Corporate
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_CORPORATE_VERSION', '4.0.0' );
define( 'ACME_CORPORATE_DIR', get_template_directory() );
define( 'ACME_CORPORATE_URI', get_template_directory_uri() );

require ACME_CORPORATE_DIR . '/inc/setup.php';
require ACME_CORPORATE_DIR . '/inc/template-functions.php';
require ACME_CORPORATE_DIR . '/inc/template-tags.php';
require ACME_CORPORATE_DIR . '/inc/class-acme-corporate-menu-walker.php';
require ACME_CORPORATE_DIR . '/inc/customizer.php';
require ACME_CORPORATE_DIR . '/inc/patterns.php';
require ACME_CORPORATE_DIR . '/inc/block-template-parts.php';
