<?php
/**
 * [team_member] and [team_directory] shortcodes (classic-editor era, still
 * used on many pages).
 *
 * @package Acme\Team
 */

namespace Acme\Team;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcode rendering.
 */
class Shortcodes {

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'init', array( $this, 'register' ) );
	}

	/**
	 * Register the shortcodes.
	 */
	public function register() {
		add_shortcode( 'team_member', array( $this, 'member' ) );
		add_shortcode( 'team_directory', array( $this, 'directory' ) );
	}

	/**
	 * [team_member id="12" fields="role,email,phone"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function member( $atts ) {
		$atts   = shortcode_atts(
			array(
				'id'     => 0,
				'fields' => 'role,email,phone',
			),
			$atts,
			'team_member'
		);
		$member = Member::get( (int) $atts['id'] );
		if ( ! $member || ! $member->is_public() ) {
			return '';
		}
		$fields = array_filter( array_map( 'trim', explode( ',', (string) $atts['fields'] ) ) );
		return $this->card( $member, $fields );
	}

	/**
	 * [team_directory department="engineering"]
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public function directory( $atts ) {
		$atts = shortcode_atts(
			array(
				'department' => '',
				'fields'     => 'role,email',
			),
			$atts,
			'team_directory'
		);
		$args = array(
			'post_type'      => Post_Type::POST_TYPE,
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 100,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'no_found_rows'  => true,
		);
		if ( $atts['department'] ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Post_Type::TAXONOMY,
					'field'    => 'slug',
					'terms'    => sanitize_title( $atts['department'] ),
				),
			);
		}
		$fields = array_filter( array_map( 'trim', explode( ',', (string) $atts['fields'] ) ) );
		$html   = '';
		foreach ( get_posts( $args ) as $post ) {
			$member = Member::get( $post );
			if ( $member ) {
				$html .= '<li>' . $this->card( $member, $fields ) . '</li>';
			}
		}
		return '' === $html ? '' : '<ul class="acme-team-directory">' . $html . '</ul>';
	}

	/**
	 * Card markup for a member.
	 *
	 * @param Member   $member Member.
	 * @param string[] $fields Fields to show.
	 * @return string
	 */
	public function card( Member $member, array $fields ) {
		$html  = '<div class="acme-team-card">';
		$photo = $member->photo_url( 'thumbnail' );
		if ( $photo ) {
			$html .= sprintf( '<img class="acme-team-card__photo" src="%s" alt="%s" />', esc_url( $photo ), esc_attr( $member->photo_alt() ) );
		}
		$html .= sprintf( '<p class="acme-team-card__name"><a href="%s">%s</a></p>', esc_url( get_permalink( $member->post() ) ), esc_html( $member->name() ) );
		foreach ( $fields as $key ) {
			switch ( $key ) {
				case 'email':
					if ( $member->email() ) {
						$html .= sprintf( '<p class="acme-team-card__email"><a href="%s">%s</a></p>', esc_url( 'mailto:' . $member->email() ), esc_html( $member->email() ) );
					}
					break;
				case 'phone':
					if ( $member->phone_href() ) {
						$html .= sprintf( '<p class="acme-team-card__phone"><a href="%s">%s</a></p>', esc_url( $member->phone_href(), array( 'tel' ) ), esc_html( $member->phone() ) );
					}
					break;
				case 'profile_url':
					if ( $member->profile_url() ) {
						$html .= sprintf( '<p class="acme-team-card__profile"><a href="%s">%s</a></p>', esc_url( $member->profile_url() ), esc_html__( 'Profile', 'acme-team' ) );
					}
					break;
				default:
					$value = $member->get_field( $key );
					if ( '' !== $value && 'photo' !== $key && 'name' !== $key ) {
						$html .= sprintf( '<p class="acme-team-card__%s">%s</p>', esc_attr( sanitize_html_class( $key ) ), esc_html( $value ) );
					}
			}
		}
		return $html . '</div>';
	}
}
