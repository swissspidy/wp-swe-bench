<?php
/**
 * Per-source rate limit on newly accepted deliveries (fixed 60 s windows).
 *
 * @package Acme\OrdersSync
 */

namespace Acme\OrdersSync;

defined( 'ABSPATH' ) || exit;

/**
 * Rate limiter.
 */
class Rate_Limiter {

	const WINDOW = 60;

	/**
	 * Settings.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param Settings $settings Settings.
	 */
	public function __construct( Settings $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Transient key of a source.
	 *
	 * @param string $source Source ID.
	 */
	private function key( string $source ): string {
		return 'acme_orders_rl_' . md5( $source );
	}

	/**
	 * Current window of a source.
	 *
	 * @param string $source Source ID.
	 * @return array{start: int, count: int}
	 */
	private function window( string $source ): array {
		$now    = time();
		$window = get_transient( $this->key( $source ) );
		if ( ! is_array( $window ) || ! isset( $window['start'], $window['count'] ) || $now - (int) $window['start'] >= self::WINDOW ) {
			$window = array(
				'start' => $now,
				'count' => 0,
			);
		}
		return array(
			'start' => (int) $window['start'],
			'count' => (int) $window['count'],
		);
	}

	/**
	 * Seconds until the source may deliver again, or 0 if it is within its budget.
	 *
	 * @param string $source Source ID.
	 */
	public function retry_after( string $source ): int {
		$limit = (int) $this->settings->get( 'rate_limit', 60 );
		if ( $limit <= 0 ) {
			return 0;
		}
		$window = $this->window( $source );
		if ( $window['count'] < $limit ) {
			return 0;
		}
		return max( 1, $window['start'] + self::WINDOW - time() );
	}

	/**
	 * Records an accepted delivery.
	 *
	 * @param string $source Source ID.
	 */
	public function hit( string $source ): void {
		$window = $this->window( $source );
		++$window['count'];
		set_transient( $this->key( $source ), $window, self::WINDOW * 2 );
	}
}
