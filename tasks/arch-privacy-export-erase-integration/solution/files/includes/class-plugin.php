<?php
/**
 * Plugin bootstrap.
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Singleton.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components.
	 *
	 * @var object[]
	 */
	private $components = array();

	/**
	 * Instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register hooks.
	 */
	public function boot() {
		add_action( 'plugins_loaded', array( Installer::class, 'maybe_upgrade' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'user_register', array( $this, 'maybe_enrol_new_user' ) );

		$this->components = array(
			'newsletter'   => new Newsletter(),
			'orders'       => new Orders(),
			'account'      => new Account(),
			'settings'     => new Settings(),
			'user_profile' => new User_Profile(),
			'privacy'      => new Privacy(),
			'retention'    => new Retention(),
		);
		foreach ( $this->components as $component ) {
			$component->register();
		}
	}

	/**
	 * A component instance.
	 *
	 * @param string $name Component name.
	 * @return object|null
	 */
	public function get( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}

	/**
	 * Load translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'acme-loyalty', false, dirname( plugin_basename( ACME_LOYALTY_FILE ) ) . '/languages' );
	}

	/**
	 * New accounts with the member role are enrolled automatically.
	 *
	 * @param int $user_id New user.
	 */
	public function maybe_enrol_new_user( $user_id ) {
		$user = get_userdata( $user_id );
		if ( $user && in_array( Members::ROLE, (array) $user->roles, true ) ) {
			Members::enrol( $user_id );
		}
	}
}
