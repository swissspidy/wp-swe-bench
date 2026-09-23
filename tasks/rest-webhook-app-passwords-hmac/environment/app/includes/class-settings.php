<?php
/**
 * Plugin settings (Settings → Order Sync).
 *
 * Stored in the `acme_orders_sync_settings` option:
 *
 *     array(
 *         'sources'   => array(
 *             'shop-eu' => array( 'label' => 'EU storefront', 'secret' => '…' ),
 *         ),
 *         'log_level' => 'info', // debug | info | error
 *     )
 *
 * Sites that were set up with 1.0/1.1 still have their single webhook secret in
 * the `acme_orders_webhook_secret` option. It belongs to the `default` source.
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Settings accessor.
 */
class Settings {

	const OPTION        = 'acme_orders_sync_settings';
	const LEGACY_SECRET = 'acme_orders_webhook_secret';
	const LOG_LEVELS    = array( 'debug', 'info', 'error' );

	/**
	 * Defaults.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'sources'   => array(),
			'log_level' => 'info',
		);
	}

	/**
	 * Registers the option with its sanitizer.
	 */
	public function register(): void {
		register_setting(
			'acme_orders_sync',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * All settings merged with the defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function all(): array {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * One setting.
	 *
	 * @param string $key     Key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public function get( string $key, $default = null ) {
		$all = $this->all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Configured sources (storefronts), including the legacy `default` source.
	 *
	 * @return array<string, array{label: string, secret: string}>
	 */
	public function sources(): array {
		$sources = array();
		foreach ( (array) $this->get( 'sources', array() ) as $id => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}
			$sources[ (string) $id ] = array(
				'label'  => (string) ( $source['label'] ?? $id ),
				'secret' => (string) ( $source['secret'] ?? '' ),
			);
		}

		// 1.0/1.1 installs: a single secret for the one storefront we had back then.
		$legacy = get_option( self::LEGACY_SECRET, '' );
		if ( is_string( $legacy ) && '' !== $legacy && ! isset( $sources['default'] ) ) {
			$sources['default'] = array(
				'label'  => __( 'Default storefront', 'acme-orders-sync' ),
				'secret' => $legacy,
			);
		}

		return $sources;
	}

	/**
	 * A single source, or null when it is not configured.
	 *
	 * @param string $id Source ID.
	 * @return array{label: string, secret: string}|null
	 */
	public function source( string $id ): ?array {
		$sources = $this->sources();
		return $sources[ $id ] ?? null;
	}

	/**
	 * Sanitizes the option on save.
	 *
	 * @param mixed $input Raw input.
	 * @return array<string, mixed>
	 */
	public function sanitize( $input ): array {
		$input  = is_array( $input ) ? $input : array();
		$output = self::defaults();

		$sources = $input['sources'] ?? array();
		if ( is_array( $sources ) ) {
			foreach ( $sources as $key => $source ) {
				if ( ! is_array( $source ) ) {
					continue;
				}
				// The settings screen posts rows (list), code may pass a map.
				$id = isset( $source['id'] ) ? $source['id'] : ( is_string( $key ) ? $key : '' );
				$id = sanitize_key( (string) $id );
				if ( '' === $id ) {
					continue;
				}
				$output['sources'][ $id ] = array(
					'label'  => sanitize_text_field( (string) ( $source['label'] ?? $id ) ),
					'secret' => trim( (string) ( $source['secret'] ?? '' ) ),
				);
			}
		}

		if ( isset( $input['log_level'] ) && in_array( $input['log_level'], self::LOG_LEVELS, true ) ) {
			$output['log_level'] = $input['log_level'];
		}

		return $output;
	}
}
