<?php
/**
 * REST controller for bookings, v2: acme-bookings/v2/bookings.
 *
 * Differences to v1:
 *  - `period` {start, end, timezone} instead of `start`/`end` (local times with offsets),
 *  - `total` {amount, currency} in ISO 4217 minor units.
 *
 * @package Acme\Bookings
 */

namespace Acme\Bookings;

defined( 'ABSPATH' ) || exit;

/**
 * Bookings v2 controller.
 */
class Bookings_V2_Controller extends Bookings_Controller {

	const NAMESPACE_V2 = 'acme-bookings/v2';

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct( self::NAMESPACE_V2 );
	}

	/**
	 * The site's timezone as an identifier.
	 *
	 * @return string
	 */
	public static function site_timezone() {
		$tz = wp_timezone_string();
		return in_array( $tz, timezone_identifiers_list(), true ) ? $tz : ( 'UTC' === $tz ? 'UTC' : $tz );
	}

	/**
	 * Whether a string is a known IANA timezone identifier.
	 *
	 * @param mixed $tz Value.
	 * @return bool
	 */
	protected static function is_timezone( $tz ) {
		return is_string( $tz ) && ( 'UTC' === $tz || in_array( $tz, timezone_identifiers_list( \DateTimeZone::ALL_WITH_BC ), true ) );
	}

	/**
	 * Timezone of a stored booking.
	 *
	 * @param object $row Row.
	 * @return string
	 */
	protected function timezone_of( $row ) {
		return self::site_timezone();
	}

	/**
	 * UTC DB value → local RFC 3339 in a timezone.
	 *
	 * @param string $mysql UTC DB value.
	 * @param string $tz    Timezone identifier.
	 * @return string|null
	 */
	protected function to_local( $mysql, $tz ) {
		$ts = acme_bookings_mysql_to_timestamp( $mysql );
		if ( null === $ts ) {
			return null;
		}
		try {
			$zone = new \DateTimeZone( $tz );
		} catch ( \Exception $e ) {
			$zone = new \DateTimeZone( 'UTC' );
		}
		return ( new \DateTimeImmutable( '@' . $ts ) )->setTimezone( $zone )->format( 'Y-m-d\TH:i:sP' );
	}

	/**
	 * Parse an RFC 3339 date-time; values without offset are local to $tz.
	 *
	 * @param string $value Date-time.
	 * @param string $tz    Timezone identifier.
	 * @return string|null UTC DB value.
	 */
	protected function parse_local( $value, $tz ) {
		$value = (string) $value;
		if ( ! preg_match( '/^(\d{4}-\d{2}-\d{2})[Tt ](\d{2}:\d{2}:\d{2})(\.\d+)?(Z|z|[+-]\d{2}(?::?\d{2})?)?$/', $value, $m ) ) {
			return null;
		}
		try {
			if ( ! empty( $m[4] ) ) {
				$dt = new \DateTimeImmutable( $m[1] . 'T' . $m[2] . strtoupper( $m[4] ) );
			} else {
				$dt = new \DateTimeImmutable( $m[1] . ' ' . $m[2], new \DateTimeZone( $tz ) );
			}
		} catch ( \Exception $e ) {
			return null;
		}
		$errors = \DateTimeImmutable::getLastErrors();
		if ( $errors && ( $errors['warning_count'] || $errors['error_count'] ) ) {
			return null;
		}
		return $dt->setTimezone( new \DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Errors about start/end are reported on `period`.
	 *
	 * @param string $field v1 field.
	 * @return string
	 */
	protected function public_field( $field ) {
		return in_array( $field, array( 'start', 'end' ), true ) ? 'period' : $field;
	}

	/**
	 * No top-level date fields in v2.
	 *
	 * @return array
	 */
	protected function date_fields() {
		return array();
	}

	/**
	 * Request → columns.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error
	 */
	protected function prepare_item_for_database( $request ) {
		$data = parent::prepare_item_for_database( $request );
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		$existing = ! empty( $request['id'] ) ? Repository::find( (int) $request['id'] ) : null;

		if ( ! isset( $request['period'] ) ) {
			return $data;
		}
		$period = (array) $request['period'];

		if ( ! $existing && ( empty( $period['start'] ) || empty( $period['end'] ) ) ) {
			return $this->invalid_param( 'period', __( 'A period needs a start and an end.', 'acme-bookings' ) );
		}

		if ( array_key_exists( 'timezone', $period ) ) {
			if ( ! self::is_timezone( $period['timezone'] ) ) {
				return $this->invalid_param( 'period', __( 'Unknown timezone.', 'acme-bookings' ) );
			}
			$tz = $period['timezone'];
		} elseif ( $existing ) {
			$tz = $this->timezone_of( $existing );
		} else {
			$tz = self::site_timezone();
		}

		foreach ( array(
			'start' => 'start_date',
			'end'   => 'end_date',
		) as $member => $column ) {
			if ( ! array_key_exists( $member, $period ) ) {
				continue;
			}
			$mysql = $this->parse_local( $period[ $member ], $tz );
			if ( null === $mysql ) {
				return $this->invalid_param( 'period', __( 'Invalid date.', 'acme-bookings' ) );
			}
			$data[ $column ] = $mysql;
		}
		return $data;
	}

	/**
	 * Row → v2 data.
	 *
	 * @param object $item Row.
	 * @return array
	 */
	protected function row_to_data( $item ) {
		$data = parent::row_to_data( $item );
		$tz   = $this->timezone_of( $item );

		$amount = (int) round( Pricing::total_for( $item ) * 100 );

		$out = array();
		foreach ( $data as $key => $value ) {
			if ( 'start' === $key ) {
				$out['period'] = array(
					'start'    => $this->to_local( $item->start_date, $tz ),
					'end'      => $this->to_local( $item->end_date, $tz ),
					'timezone' => $tz,
				);
			} elseif ( 'end' === $key || 'currency' === $key ) {
				continue;
			} elseif ( 'total' === $key ) {
				$out['total'] = array(
					'amount'   => $amount,
					'currency' => Pricing::currency(),
				);
			} else {
				$out[ $key ] = $value;
			}
		}
		return $out;
	}

	/**
	 * v2 schema properties.
	 *
	 * @return array
	 */
	protected function get_schema_properties() {
		$props = parent::get_schema_properties();
		$out   = array();
		foreach ( $props as $key => $prop ) {
			if ( 'start' === $key ) {
				$out['period'] = array(
					'description' => __( 'Stay period, in the booking\'s timezone.', 'acme-bookings' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'required'    => true,
					'properties'  => array(
						'start'    => array(
							'description' => __( 'Check-in (local time with UTC offset).', 'acme-bookings' ),
							'type'        => 'string',
							'format'      => 'date-time',
						),
						'end'      => array(
							'description' => __( 'Check-out (local time with UTC offset).', 'acme-bookings' ),
							'type'        => 'string',
							'format'      => 'date-time',
						),
						'timezone' => array(
							'description' => __( 'IANA timezone identifier.', 'acme-bookings' ),
							'type'        => 'string',
						),
					),
				);
			} elseif ( 'end' === $key || 'currency' === $key ) {
				continue;
			} elseif ( 'total' === $key ) {
				$out['total'] = array(
					'description' => __( 'Total price in minor units of the currency.', 'acme-bookings' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
					'properties'  => array(
						'amount'   => array(
							'description' => __( 'Amount in the currency\'s minor unit (ISO 4217).', 'acme-bookings' ),
							'type'        => 'integer',
						),
						'currency' => array(
							'description' => __( 'ISO 4217 currency code.', 'acme-bookings' ),
							'type'        => 'string',
						),
					),
				);
			} else {
				$out[ $key ] = $prop;
			}
		}
		return $out;
	}
}
