<?php
/**
 * Plugin Name:       Acme Glossary
 * Description:       Glossary of technical terms with accessible tooltips in posts ("Glossary term" text format, legacy [glossary] shortcode) and an A–Z index.
 * Version:           2.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Acme Docs Team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       acme-glossary
 * Domain Path:       /languages
 *
 * @package Acme\Glossary
 */

defined( 'ABSPATH' ) || exit;

define( 'ACME_GLOSSARY_VERSION', '2.0.0' );
define( 'ACME_GLOSSARY_FILE', __FILE__ );
define( 'ACME_GLOSSARY_DIR', plugin_dir_path( __FILE__ ) );
define( 'ACME_GLOSSARY_URL', plugin_dir_url( __FILE__ ) );

require_once ACME_GLOSSARY_DIR . 'includes/functions.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-post-type.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-term-cache.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-shortcodes.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-rest.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-index-block.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-assets.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-renderer.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-editor.php';
require_once ACME_GLOSSARY_DIR . 'includes/class-plugin.php';

Acme\Glossary\Plugin::instance()->boot();
