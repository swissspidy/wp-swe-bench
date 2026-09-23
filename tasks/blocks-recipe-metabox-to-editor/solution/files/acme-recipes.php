<?php
/**
 * Plugin Name:       Acme Recipes
 * Description:       Recipes for the Acme food blog: a "Recipe" post type with ingredients, times, servings and difficulty, a "Recipe details" panel in the block editor and a recipe card (automatic or placed with the Recipe card block).
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Web Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-recipes
 * Domain Path:       /languages
 *
 * @package Acme\Recipes
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_RECIPES_VERSION', '2.0.0' );
define( 'ACME_RECIPES_FILE', __FILE__ );
define( 'ACME_RECIPES_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_RECIPES_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_RECIPES_DIR . 'includes/functions.php';
require_once ACME_RECIPES_DIR . 'includes/class-post-type.php';
require_once ACME_RECIPES_DIR . 'includes/class-meta.php';
require_once ACME_RECIPES_DIR . 'includes/class-editor.php';
require_once ACME_RECIPES_DIR . 'includes/class-card.php';
require_once ACME_RECIPES_DIR . 'includes/class-schema.php';
require_once ACME_RECIPES_DIR . 'includes/class-plugin.php';

Acme\Recipes\Plugin::instance()->boot();
