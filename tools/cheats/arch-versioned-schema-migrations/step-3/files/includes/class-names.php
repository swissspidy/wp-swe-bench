<?php
/**
 * Splitting and joining personal names.
 *
 * @package Acme\CRM
 */

namespace Acme\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * The name rules agreed with Sales (used by the backfill and for every legacy "full name"
 * input: the website form, the REST `name` field and acme_crm_create_contact()).
 */
class Names {

	/**
	 * Lower-case particles that belong to the last name when they directly precede it.
	 */
	const PARTICLES = array( 'van', 'von', 'der', 'den', 'de', 'del', 'della', 'di', 'da', 'du', 'la', 'le', 'ter', 'ten', 'bin', 'al' );

	/**
	 * Generational suffixes (compared case-insensitively, without a trailing period).
	 */
	const SUFFIXES = array( 'jr', 'sr', 'ii', 'iii', 'iv' );

	/**
	 * Collapse whitespace.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	public static function normalize( $value ) {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $value ) );
	}

	/**
	 * Split a full name.
	 *
	 * @param string $name Full name.
	 * @return array{0:string,1:string} First name, last name.
	 */
	public static function split( $name ) {
		$name = self::normalize( $name );
		if ( '' === $name ) {
			return array( '', '' );
		}

		// "Last, First".
		if ( 1 === substr_count( $name, ',' ) ) {
			list( $last, $first ) = explode( ',', $name );
			return array( self::normalize( $first ), self::normalize( $last ) );
		}

		$name   = self::normalize( str_replace( ',', ' ', $name ) );
		$tokens = explode( ' ', $name );
		if ( 1 === count( $tokens ) ) {
			return array( $tokens[0], '' );
		}

		$suffix = '';
		if ( count( $tokens ) >= 3 && in_array( strtolower( rtrim( end( $tokens ), '.' ) ), self::SUFFIXES, true ) ) {
			$suffix = array_pop( $tokens );
		}

		$last = array( array_pop( $tokens ) );
		while ( count( $tokens ) > 1 && in_array( end( $tokens ), self::PARTICLES, true ) ) {
			array_unshift( $last, array_pop( $tokens ) );
		}
		if ( '' !== $suffix ) {
			$last[] = $suffix;
		}

		return array( implode( ' ', $tokens ), implode( ' ', $last ) );
	}

	/**
	 * Join first and last name.
	 *
	 * @param string $first First name.
	 * @param string $last  Last name.
	 * @return string
	 */
	public static function join( $first, $last ) {
		return self::normalize( $first . ' ' . $last );
	}
}
