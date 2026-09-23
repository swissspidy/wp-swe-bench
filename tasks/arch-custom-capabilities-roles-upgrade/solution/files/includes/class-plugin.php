<?php
/**
 * Bootstrap.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Wires up the components.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boots the plugin once.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Hooks.
	 */
	private function boot() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		( new Upgrader() )->register();
		( new Capabilities() )->register();
		( new Story_Post_Type() )->register();
		( new Approval() )->register();
		( new Freelancers() )->register();
		( new Credits() )->register();

		add_action(
			'rest_api_init',
			static function () {
				( new REST_Approval_Controller() )->register_routes();
			}
		);

		if ( is_admin() ) {
			( new Admin\Stories_List() )->register();
			( new Admin\Approval_Meta_Box() )->register();
		}
	}

	/**
	 * Translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-newsroom', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
