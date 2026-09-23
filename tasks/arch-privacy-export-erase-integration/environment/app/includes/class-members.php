<?php
/**
 * Loyalty members: profile data stored as user meta.
 *
 * User meta written by this plugin:
 *
 * | key                          | since | format                                            |
 * |------------------------------|-------|---------------------------------------------------|
 * | acme_loyalty_member_since    | 1.0   | Y-m-d                                             |
 * | acme_loyalty_tier            | 1.2   | tier slug, manual override (empty = automatic)    |
 * | acme_loyalty_phone           | 1.0   | free text                                         |
 * | acme_loyalty_referral_code   | 1.3   | 8 chars, unique                                   |
 * | _acme_loyalty_referred_by    | 1.3   | user ID of the referrer                           |
 * | acme_loyalty_birthday        | 2.0   | Y-m-d                                             |
 * | acme_loyalty_preferences     | 2.0   | array( 'channels' => string[], 'store' => string ) |
 *
 * Legacy (1.x, still read, migrated lazily on the next profile save):
 *
 * | acme_loyalty_dob             | 1.0   | d/m/Y                                             |
 * | acme_loyalty_prefs           | 1.0   | "email,sms|Downtown" (channels|store)             |
 *
 * @package Acme\Loyalty
 */

namespace Acme\Loyalty;

defined( 'ABSPATH' ) || exit;

/**
 * Member profile access.
 */
class Members {

	const ROLE = 'loyalty_member';

	/**
	 * Register the "Loyalty member" role (customers who signed up in store or online).
	 */
	public static function register_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, __( 'Loyalty member', 'acme-loyalty' ), array( 'read' => true ) );
		}
	}

	/**
	 * Is the user a loyalty member (has a member-since date)?
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public static function is_member( $user_id ) {
		return '' !== (string) get_user_meta( $user_id, 'acme_loyalty_member_since', true );
	}

	/**
	 * Enrol a user: member-since date, referral code, welcome bonus.
	 *
	 * @param int $user_id     User ID.
	 * @param int $referred_by Optional referrer user ID.
	 */
	public static function enrol( $user_id, $referred_by = 0 ) {
		if ( self::is_member( $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, 'acme_loyalty_member_since', gmdate( 'Y-m-d' ) );
		update_user_meta( $user_id, 'acme_loyalty_referral_code', self::generate_referral_code() );
		if ( $referred_by && get_userdata( $referred_by ) ) {
			update_user_meta( $user_id, '_acme_loyalty_referred_by', (int) $referred_by );
			Ledger::add( $referred_by, 100, 'referral', array( 'note' => 'Referral bonus' ) );
		}
		$settings = acme_loyalty_settings();
		if ( (int) $settings['welcome_bonus'] > 0 ) {
			Ledger::add( $user_id, (int) $settings['welcome_bonus'], 'welcome' );
		}
	}

	/**
	 * Normalized profile of a member (reads 1.x keys as a fallback).
	 *
	 * @param int $user_id User ID.
	 * @return array{member_since:string, tier:string, tier_override:string, birthday:string, phone:string, referral_code:string, referred_by:int, channels:string[], store:string}
	 */
	public static function get_profile( $user_id ) {
		$birthday = (string) get_user_meta( $user_id, 'acme_loyalty_birthday', true );
		if ( '' === $birthday ) {
			$birthday = self::legacy_dob( (string) get_user_meta( $user_id, 'acme_loyalty_dob', true ) );
		}

		$prefs = get_user_meta( $user_id, 'acme_loyalty_preferences', true );
		if ( ! is_array( $prefs ) ) {
			$prefs = self::legacy_prefs( (string) get_user_meta( $user_id, 'acme_loyalty_prefs', true ) );
		}
		$channels = isset( $prefs['channels'] ) ? array_values( array_intersect( (array) $prefs['channels'], array_keys( acme_loyalty_channels() ) ) ) : array();

		$override = (string) get_user_meta( $user_id, 'acme_loyalty_tier', true );

		return array(
			'member_since'  => (string) get_user_meta( $user_id, 'acme_loyalty_member_since', true ),
			'tier'          => '' !== $override ? $override : self::computed_tier( $user_id ),
			'tier_override' => $override,
			'birthday'      => $birthday,
			'phone'         => (string) get_user_meta( $user_id, 'acme_loyalty_phone', true ),
			'referral_code' => (string) get_user_meta( $user_id, 'acme_loyalty_referral_code', true ),
			'referred_by'   => (int) get_user_meta( $user_id, '_acme_loyalty_referred_by', true ),
			'channels'      => $channels,
			'store'         => isset( $prefs['store'] ) ? (string) $prefs['store'] : '',
		);
	}

	/**
	 * Save the editable part of a profile (always in the 2.x format).
	 *
	 * @param int   $user_id User ID.
	 * @param array $data    birthday, phone, channels, store.
	 */
	public static function save_profile( $user_id, array $data ) {
		if ( array_key_exists( 'birthday', $data ) ) {
			$birthday = self::sanitize_date( $data['birthday'] );
			update_user_meta( $user_id, 'acme_loyalty_birthday', $birthday );
			delete_user_meta( $user_id, 'acme_loyalty_dob' );
		}
		if ( array_key_exists( 'phone', $data ) ) {
			update_user_meta( $user_id, 'acme_loyalty_phone', sanitize_text_field( $data['phone'] ) );
		}
		if ( array_key_exists( 'channels', $data ) || array_key_exists( 'store', $data ) ) {
			$current  = self::get_profile( $user_id );
			$channels = array_key_exists( 'channels', $data ) ? array_values( array_intersect( array_map( 'sanitize_key', (array) $data['channels'] ), array_keys( acme_loyalty_channels() ) ) ) : $current['channels'];
			$store    = array_key_exists( 'store', $data ) ? sanitize_text_field( $data['store'] ) : $current['store'];
			update_user_meta(
				$user_id,
				'acme_loyalty_preferences',
				array(
					'channels' => $channels,
					'store'    => $store,
				)
			);
			delete_user_meta( $user_id, 'acme_loyalty_prefs' );
		}
	}

	/**
	 * Tier from lifetime points.
	 *
	 * @param int $user_id User ID.
	 * @return string Tier slug.
	 */
	public static function computed_tier( $user_id ) {
		$lifetime = Ledger::lifetime_points( $user_id );
		$tier     = 'bronze';
		foreach ( acme_loyalty_tiers() as $slug => $def ) {
			if ( $lifetime >= (int) $def['threshold'] ) {
				$tier = $slug;
			}
		}
		return $tier;
	}

	/**
	 * Channel labels joined for display ("Email, SMS").
	 *
	 * @param string[] $channels Channel slugs.
	 * @return string
	 */
	public static function format_channels( array $channels ) {
		$labels = acme_loyalty_channels();
		$out    = array();
		foreach ( $channels as $slug ) {
			if ( isset( $labels[ $slug ] ) ) {
				$out[] = $labels[ $slug ];
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * Unique 8 character referral code.
	 *
	 * @return string
	 */
	public static function generate_referral_code() {
		do {
			$code  = strtoupper( wp_generate_password( 8, false ) );
			$taken = get_users(
				array(
					'meta_key'   => 'acme_loyalty_referral_code', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value' => $code, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'fields'     => 'ID',
					'number'     => 1,
				)
			);
		} while ( $taken );
		return $code;
	}

	/**
	 * Y-m-d or empty.
	 *
	 * @param mixed $value Date input.
	 * @return string
	 */
	public static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		$dt    = \DateTime::createFromFormat( '!Y-m-d', $value );
		return ( $dt && $dt->format( 'Y-m-d' ) === $value ) ? $value : '';
	}

	/**
	 * Convert a 1.x d/m/Y birthday.
	 *
	 * @param string $value Legacy value.
	 * @return string Y-m-d or empty.
	 */
	private static function legacy_dob( $value ) {
		if ( '' === $value ) {
			return '';
		}
		$dt = \DateTime::createFromFormat( '!d/m/Y', $value );
		return $dt ? $dt->format( 'Y-m-d' ) : '';
	}

	/**
	 * Parse 1.x preferences ("email,sms|Downtown").
	 *
	 * @param string $value Legacy value.
	 * @return array{channels:string[], store:string}
	 */
	private static function legacy_prefs( $value ) {
		if ( '' === $value ) {
			return array(
				'channels' => array(),
				'store'    => '',
			);
		}
		$parts = explode( '|', $value, 2 );
		return array(
			'channels' => array_filter( array_map( 'trim', explode( ',', strtolower( $parts[0] ) ) ) ),
			'store'    => isset( $parts[1] ) ? trim( $parts[1] ) : '',
		);
	}
}
