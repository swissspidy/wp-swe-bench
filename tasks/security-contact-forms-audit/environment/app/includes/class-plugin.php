<?php
/**
 * Main plugin class.
 *
 * @package Acme\Forms
 */

namespace Acme\Forms;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the components together.
 */
final class Plugin {

	/**
	 * Instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Components by key.
	 *
	 * @var object[]
	 */
	private $components = array();

	/**
	 * Singleton.
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
	 * Boot.
	 */
	public function boot() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
	}

	/**
	 * Init on plugins_loaded.
	 */
	public function init() {
		load_plugin_textdomain( 'acme-forms', false, dirname( plugin_basename( ACME_FORMS_FILE ) ) . '/languages' );
		Installer::maybe_upgrade();

		$this->components = array(
			'forms'         => new Forms(),
			'renderer'      => new Renderer(),
			'handler'       => new Submission_Handler(),
			'notifications' => new Notifications(),
		);

		if ( is_admin() ) {
			$this->components['admin']     = new Admin\Admin();
			$this->components['inbox']     = new Admin\Submissions_Page();
			$this->components['settings']  = new Admin\Settings_Page();
			$this->components['export']    = new Admin\Export();
			$this->components['dashboard'] = new Admin\Dashboard_Widget();
		}

		foreach ( $this->components as $component ) {
			$component->register_hooks();
		}

		/**
		 * Fires when Acme Forms is ready.
		 *
		 * @param Plugin $plugin The plugin.
		 */
		do_action( 'acme_forms_loaded', $this );
	}

	/**
	 * A component.
	 *
	 * @param string $key Key.
	 * @return object|null
	 */
	public function get( $key ) {
		return isset( $this->components[ $key ] ) ? $this->components[ $key ] : null;
	}
}
