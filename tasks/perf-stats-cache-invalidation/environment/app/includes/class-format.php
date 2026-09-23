<?php
/**
 * HTML fragments shared by the widget, the admin page and the shortcode.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Formatting helpers.
 */
class Format {

	/**
	 * "March 2025" for "2025-03".
	 *
	 * @param string $month YYYY-MM.
	 * @return string
	 */
	public static function month_label( $month ) {
		return date_i18n( 'F Y', strtotime( $month . '-01 00:00:00' ) );
	}

	/**
	 * Summary list (totals).
	 *
	 * @param array $stats Stats.
	 * @return string
	 */
	public static function totals( array $stats ) {
		$items = array(
			/* translators: %s: number of posts */
			'posts'    => sprintf( _n( '%s published post', '%s published posts', $stats['posts']['total'], 'acme-dashboard-stats' ), number_format_i18n( $stats['posts']['total'] ) ),
			/* translators: %s: number of words */
			'words'    => sprintf( _n( '%s word', '%s words', $stats['words']['total'], 'acme-dashboard-stats' ), number_format_i18n( $stats['words']['total'] ) ),
			/* translators: %s: number of comments */
			'comments' => sprintf( _n( '%s comment', '%s comments', $stats['comments']['approved'], 'acme-dashboard-stats' ), number_format_i18n( $stats['comments']['approved'] ) ),
		);
		$html  = '<ul class="acme-stats__totals">';
		foreach ( $items as $key => $label ) {
			$html .= sprintf( '<li class="acme-stats__total acme-stats__total--%s">%s</li>', esc_attr( $key ), esc_html( $label ) );
		}
		return $html . '</ul>';
	}

	/**
	 * A two-column table.
	 *
	 * @param string $caption Caption.
	 * @param array  $rows    label => number.
	 * @param string $class   Extra class.
	 * @return string
	 */
	public static function table( $caption, array $rows, $class = '' ) {
		$html = sprintf( '<table class="acme-stats__table %s"><caption>%s</caption><tbody>', esc_attr( $class ), esc_html( $caption ) );
		foreach ( $rows as $label => $number ) {
			$html .= sprintf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), esc_html( number_format_i18n( $number ) ) );
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Full report (dashboard widget and admin page).
	 *
	 * @param array $stats Stats.
	 * @return string
	 */
	public static function report( array $stats ) {
		$html = self::totals( $stats );

		$authors = array();
		foreach ( $stats['posts']['by_author'] as $row ) {
			$authors[ $row['name'] ] = $row['count'];
		}
		$html .= self::table( __( 'Posts by author', 'acme-dashboard-stats' ), $authors, 'acme-stats__authors' );

		$cats = array();
		foreach ( $stats['posts']['by_category'] as $row ) {
			$cats[ $row['name'] ] = $row['count'];
		}
		$html .= self::table( __( 'Posts by category', 'acme-dashboard-stats' ), $cats, 'acme-stats__categories' );

		$months = array();
		foreach ( array_slice( $stats['posts']['by_month'], -12, null, true ) as $month => $count ) {
			$months[ self::month_label( $month ) ] = $count;
		}
		$html .= self::table( __( 'Posts per month (last 12 months with posts)', 'acme-dashboard-stats' ), $months, 'acme-stats__months' );

		$html .= self::table(
			__( 'Comments', 'acme-dashboard-stats' ),
			array(
				__( 'Approved', 'acme-dashboard-stats' ) => $stats['comments']['approved'],
				__( 'Awaiting moderation', 'acme-dashboard-stats' ) => $stats['comments']['pending'],
				__( 'Spam', 'acme-dashboard-stats' )     => $stats['comments']['spam'],
			),
			'acme-stats__comments'
		);

		if ( $stats['comments']['top_posts'] ) {
			$html .= '<h3>' . esc_html__( 'Most discussed', 'acme-dashboard-stats' ) . '</h3><ol class="acme-stats__top">';
			foreach ( $stats['comments']['top_posts'] as $row ) {
				$html .= sprintf( '<li><a href="%s">%s</a> (%s)</li>', esc_url( get_permalink( $row['id'] ) ), esc_html( $row['title'] ), esc_html( number_format_i18n( $row['count'] ) ) );
			}
			$html .= '</ol>';
		}

		/* translators: %s: date and time */
		$html .= '<p class="acme-stats__generated">' . esc_html( sprintf( __( 'Generated %s', 'acme-dashboard-stats' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $stats['generated_at'] ) ), get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) ) . '</p>';
		return '<div class="acme-stats" data-scope="' . esc_attr( $stats['scope'] ) . '">' . $html . '</div>';
	}
}
