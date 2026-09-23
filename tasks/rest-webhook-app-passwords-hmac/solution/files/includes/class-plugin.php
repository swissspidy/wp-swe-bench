<?php
/**
 * Plugin bootstrap / service container.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the plugin's services together.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	public $settings;

	/**
	 * Logger.
	 *
	 * @var Logger
	 */
	public $logger;

	/**
	 * Order processor.
	 *
	 * @var Order_Processor
	 */
	public $processor;

	/**
	 * Webhook REST controller.
	 *
	 * @var Webhook_Controller
	 */
	public $webhooks;

	/**
	 * Received events.
	 *
	 * @var Delivery_Store
	 */
	public $deliveries;

	/**
	 * Background processing.
	 *
	 * @var Queue
	 */
	public $queue;

	/**
	 * Delivery status REST controller.
	 *
	 * @var Deliveries_Controller
	 */
	public $deliveries_controller;

	/**
	 * Returns the instance.
	 */
	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Hooked to plugins_loaded.
	 */
	public static function boot(): void {
		self::instance()->init();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->settings  = new Settings();
		$this->logger    = new Logger( $this->settings );
		$this->processor             = new Order_Processor( $this->logger );
		$this->deliveries            = new Delivery_Store();
		$this->queue                 = new Queue( $this->deliveries, $this->processor, $this->logger );
		$this->webhooks              = new Webhook_Controller( $this->settings, $this->logger, $this->processor, $this->deliveries, $this->queue, new Rate_Limiter( $this->settings ) );
		$this->deliveries_controller = new Deliveries_Controller( $this->deliveries );
	}

	/**
	 * Registers hooks.
	 */
	private function init(): void {
		Installer::maybe_upgrade();

		add_action( 'init', array( Order_Post_Type::class, 'register' ) );
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'rest_api_init', array( $this->webhooks, 'register_routes' ) );
		add_action( 'rest_api_init', array( $this->deliveries_controller, 'register_routes' ) );
		$this->queue->hooks();

		if ( is_admin() ) {
			( new Admin\Settings_Page( $this->settings ) )->hooks();
			( new Admin\Log_Page( $this->logger ) )->hooks();
			Order_Post_Type::admin_hooks();
		}

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'acme-orders', new CLI_Command( $this->settings, $this->processor ) );
		}
	}

	/**
	 * Loads translations.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain( 'acme-orders-sync', false, dirname( plugin_basename( PLUGIN_FILE ) ) . '/languages' );
	}
}
