<?php
/**
 * Author details in the RSS feed (our newsletter tool and the partner aggregator read them).
 *
 * Feeds are cached and shared by aggregators, so they only ever contain public data, whoever
 * requests them.
 *
 * @package Acme\Members
 */

namespace Acme\Members;

defined( 'ABSPATH' ) || exit;

/**
 * Feed additions.
 */
class Feeds {

	const NS = 'https://acme.example/ns/members/1.0';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'rss2_ns', array( $this, 'namespace_attribute' ) );
		add_action( 'rss2_item', array( $this, 'item' ) );
		add_filter( 'the_author', array( $this, 'author_with_company' ) );
	}

	/**
	 * xmlns:acme on the <rss> element.
	 */
	public function namespace_attribute() {
		echo 'xmlns:acme="' . esc_url( self::NS ) . '"' . "\n\t";
	}

	/**
	 * <acme:jobTitle>, <acme:company>, <acme:city> for the post author.
	 */
	public function item() {
		$author_id = (int) get_post_field( 'post_author', get_the_ID() );
		if ( ! Members::is_member( $author_id ) ) {
			return;
		}
		$map    = array(
			'job_title' => 'jobTitle',
			'company'   => 'company',
			'city'      => 'city',
		);
		$public = Visibility::visible_fields( $author_id, 0 );
		foreach ( $map as $key => $element ) {
			$value = isset( $public[ $key ] ) ? $public[ $key ] : '';
			if ( '' !== $value ) {
				printf( "\t\t<acme:%1\$s>%2\$s</acme:%1\$s>\n", esc_html( $element ), esc_html( $value ) );
			}
		}
	}

	/**
	 * In feeds, show "Name (Company)" as the author.
	 *
	 * @param string $name Display name.
	 * @return string
	 */
	public function author_with_company( $name ) {
		if ( ! is_feed() || ! in_the_loop() ) {
			return $name;
		}
		$author_id = (int) get_the_author_meta( 'ID' );
		if ( ! $author_id || ! Members::is_member( $author_id ) ) {
			return $name;
		}
		$public  = Visibility::visible_fields( $author_id, 0 );
		$company = isset( $public['company'] ) ? $public['company'] : '';
		return '' !== $company ? sprintf( '%s (%s)', $name, $company ) : $name;
	}
}
