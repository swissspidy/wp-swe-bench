<?php
/**
 * Pricing: nights, quotes, totals and currency helpers.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Pricing.
 */
class Pricing {

	/** Standard check-in time (UTC) used when only a date is given. */
	const CHECKIN_TIME = '14:00:00';

	/** Standard check-out time (UTC) used when only a date is given. */
	const CHECKOUT_TIME = '11:00:00';

	/**
	 * ISO 4217 currencies whose minor unit is not 1/100.
	 *
	 * @var array<string,int>
	 */
	const DECIMALS = array(
		'JPY' => 0,
		'KRW' => 0,
		'ISK' => 0,
		'CLP' => 0,
		'VND' => 0,
		'BHD' => 3,
		'JOD' => 3,
		'KWD' => 3,
		'OMR' => 3,
		'TND' => 3,
	);

	/**
	 * Shop currency (ISO 4217), from Settings → Bookings.
	 *
	 * @return string
	 */
	public static function currency() {
		$currency = strtoupper( (string) get_option( 'acme_bookings_currency', 'EUR' ) );
		if ( ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$currency = 'EUR';
		}
		/**
		 * Filters the booking currency.
		 *
		 * @param string $currency ISO 4217 code.
		 */
		return (string) apply_filters( 'acme_bookings_currency', $currency );
	}

	/**
	 * Number of decimals of a currency's minor unit.
	 *
	 * @param string|null $currency ISO code; defaults to the shop currency.
	 * @return int
	 */
	public static function currency_decimals( $currency = null ) {
		$currency = strtoupper( null === $currency ? self::currency() : (string) $currency );
		return isset( self::DECIMALS[ $currency ] ) ? self::DECIMALS[ $currency ] : 2;
	}

	/**
	 * Number of nights between two timestamps (calendar days, UTC). At least 1.
	 *
	 * @param int $start Start timestamp.
	 * @param int $end   End timestamp.
	 * @return int
	 */
	public static function nights( $start, $end ) {
		$utc  = new \DateTimeZone( 'UTC' );
		$from = ( new \DateTimeImmutable( '@' . (int) $start ) )->setTimezone( $utc )->setTime( 0, 0 );
		$to   = ( new \DateTimeImmutable( '@' . (int) $end ) )->setTimezone( $utc )->setTime( 0, 0 );
		$days = (int) $from->diff( $to )->format( '%r%a' );
		return max( 1, $days );
	}

	/**
	 * Price of a stay.
	 *
	 * @param int $room_id Room ID.
	 * @param int $start   Start timestamp.
	 * @param int $end     End timestamp.
	 * @return float Major units, rounded to the currency's decimals.
	 */
	public static function quote( $room_id, $start, $end ) {
		$total = self::nights( $start, $end ) * Rooms::rate( $room_id );
		/**
		 * Filters a quote (e.g. seasonal pricing).
		 *
		 * @param float $total   Total in major units.
		 * @param int   $room_id Room ID.
		 * @param int   $start   Start timestamp.
		 * @param int   $end     End timestamp.
		 */
		$total = (float) apply_filters( 'acme_bookings_quote', $total, $room_id, $start, $end );
		return round( $total, self::currency_decimals() );
	}

	/**
	 * Total of a stored booking. Bookings made before 1.2 have no stored
	 * total: they are priced with the room's current rate.
	 *
	 * @param object $row Booking row.
	 * @return float
	 */
	public static function total_for( $row ) {
		if ( isset( $row->total ) && null !== $row->total && '' !== $row->total ) {
			return round( (float) $row->total, self::currency_decimals() );
		}
		return self::quote(
			(int) $row->room_id,
			(int) acme_bookings_mysql_to_timestamp( $row->start_date ),
			(int) acme_bookings_mysql_to_timestamp( $row->end_date )
		);
	}

	/**
	 * Format an amount for display ("€ 258.00" style is left to the locale).
	 *
	 * @param float       $amount   Major units.
	 * @param string|null $currency ISO code.
	 * @return string
	 */
	public static function format( $amount, $currency = null ) {
		$currency = null === $currency ? self::currency() : $currency;
		return number_format_i18n( (float) $amount, self::currency_decimals( $currency ) ) . ' ' . $currency;
	}
}
