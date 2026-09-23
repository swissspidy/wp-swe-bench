<?php
/**
 * Course model.
 *
 * @package Acme\Courses
 */

namespace Acme\Courses;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Read-only view of a course post, normalizing the different meta formats we
 * have stored over the years:
 *
 * - 1.0–1.3 stored `acme_course_price` as free text ("$29", "1,299.50", "49")
 *   and `acme_course_duration` as free text ("3 weeks", "90 min", "2h").
 * - 1.4+ stores `_acme_course_price_cents` (int) and `_acme_course_duration`
 *   (array{value:int, unit:string}).
 *
 * The legacy keys were never migrated (the importer still writes them), so
 * always go through this class instead of reading meta directly.
 */
final class Course {

	const META_PRICE          = '_acme_course_price_cents';
	const META_DURATION       = '_acme_course_duration';
	const META_STATUS         = '_acme_course_status';
	const META_ENROLL_URL     = '_acme_course_enroll_url';
	const LEGACY_META_PRICE    = 'acme_course_price';
	const LEGACY_META_DURATION = 'acme_course_duration';

	/**
	 * The post.
	 *
	 * @var WP_Post
	 */
	private $post;

	/**
	 * Constructor.
	 *
	 * @param WP_Post $post Course post.
	 */
	private function __construct( WP_Post $post ) {
		$this->post = $post;
	}

	/**
	 * Returns a Course for a post (ID or object) or null if it is not a course.
	 *
	 * @param int|WP_Post|null $post Post, post ID or null for the current post.
	 * @return Course|null
	 */
	public static function from_post( $post = null ) {
		$post = get_post( $post );
		if ( ! $post instanceof WP_Post || Post_Types::POST_TYPE !== $post->post_type ) {
			return null;
		}
		return new self( $post );
	}

	/**
	 * Post object.
	 *
	 * @return WP_Post
	 */
	public function post() {
		return $this->post;
	}

	/**
	 * Post ID.
	 *
	 * @return int
	 */
	public function id() {
		return (int) $this->post->ID;
	}

	/**
	 * Price in cents, 0 for free courses, null when no price was entered.
	 *
	 * @return int|null
	 */
	public function price_cents() {
		$cents = get_post_meta( $this->post->ID, self::META_PRICE, true );
		if ( '' !== $cents && null !== $cents && is_numeric( $cents ) ) {
			return max( 0, (int) $cents );
		}

		$legacy = get_post_meta( $this->post->ID, self::LEGACY_META_PRICE, true );
		if ( ! is_string( $legacy ) && ! is_numeric( $legacy ) ) {
			return null;
		}
		$legacy = trim( (string) $legacy );
		if ( '' === $legacy ) {
			return null;
		}
		if ( in_array( strtolower( $legacy ), array( 'free', '0', '$0', '0.00' ), true ) ) {
			return 0;
		}
		$number = preg_replace( '/[^0-9.]/', '', str_replace( ',', '', $legacy ) );
		if ( '' === $number || ! is_numeric( $number ) ) {
			return null;
		}
		return (int) round( (float) $number * 100 );
	}

	/**
	 * Supported duration units and their labels.
	 *
	 * @return array<string, array{0:string, 1:string}> Singular/plural label patterns.
	 */
	public static function duration_units() {
		return array(
			'minutes' => array( 'minute', 'minutes' ),
			'hours'   => array( 'hour', 'hours' ),
			'days'    => array( 'day', 'days' ),
			'weeks'   => array( 'week', 'weeks' ),
		);
	}

	/**
	 * Duration as array{value:int, unit:string} or null.
	 *
	 * @return array|null
	 */
	public function duration() {
		$duration = get_post_meta( $this->post->ID, self::META_DURATION, true );
		if ( is_array( $duration ) && isset( $duration['value'], $duration['unit'] ) && (int) $duration['value'] > 0 ) {
			$unit = (string) $duration['unit'];
			if ( isset( self::duration_units()[ $unit ] ) ) {
				return array(
					'value' => (int) $duration['value'],
					'unit'  => $unit,
				);
			}
		}

		$legacy = get_post_meta( $this->post->ID, self::LEGACY_META_DURATION, true );
		if ( is_string( $legacy ) && '' !== trim( $legacy ) ) {
			return self::parse_legacy_duration( $legacy );
		}
		return null;
	}

	/**
	 * Parses the free-text durations from 1.x ("3 weeks", "90 min", "2h", "1 day").
	 *
	 * @param string $text Legacy duration.
	 * @return array|null
	 */
	public static function parse_legacy_duration( $text ) {
		if ( ! preg_match( '/^\s*(\d+)\s*([a-z]+)\.?\s*$/i', $text, $m ) ) {
			return null;
		}
		$map = array(
			'm'       => 'minutes',
			'min'     => 'minutes',
			'mins'    => 'minutes',
			'minute'  => 'minutes',
			'minutes' => 'minutes',
			'h'       => 'hours',
			'hr'      => 'hours',
			'hrs'     => 'hours',
			'hour'    => 'hours',
			'hours'   => 'hours',
			'd'       => 'days',
			'day'     => 'days',
			'days'    => 'days',
			'w'       => 'weeks',
			'wk'      => 'weeks',
			'wks'     => 'weeks',
			'week'    => 'weeks',
			'weeks'   => 'weeks',
		);
		$unit = strtolower( $m[2] );
		if ( ! isset( $map[ $unit ] ) || (int) $m[1] <= 0 ) {
			return null;
		}
		return array(
			'value' => (int) $m[1],
			'unit'  => $map[ $unit ],
		);
	}

	/**
	 * Human-readable duration ("6 weeks", "1 week", "90 minutes") or ''.
	 *
	 * @return string
	 */
	public function duration_label() {
		$duration = $this->duration();
		if ( ! $duration ) {
			return '';
		}
		$value = $duration['value'];
		switch ( $duration['unit'] ) {
			case 'minutes':
				/* translators: %d: number of minutes. */
				$label = sprintf( _n( '%d minute', '%d minutes', $value, 'acme-courses' ), $value );
				break;
			case 'hours':
				/* translators: %d: number of hours. */
				$label = sprintf( _n( '%d hour', '%d hours', $value, 'acme-courses' ), $value );
				break;
			case 'days':
				/* translators: %d: number of days. */
				$label = sprintf( _n( '%d day', '%d days', $value, 'acme-courses' ), $value );
				break;
			default:
				/* translators: %d: number of weeks. */
				$label = sprintf( _n( '%d week', '%d weeks', $value, 'acme-courses' ), $value );
		}

		/**
		 * Filters the human-readable course duration.
		 *
		 * @param string $label    Label.
		 * @param array  $duration Duration array.
		 * @param Course $course   Course.
		 */
		return (string) apply_filters( 'acme_courses_duration_label', $label, $duration, $this );
	}

	/**
	 * Enrollment status: open, closed or waitlist.
	 *
	 * @return string
	 */
	public function enrollment_status() {
		return self::sanitize_status( get_post_meta( $this->post->ID, self::META_STATUS, true ) );
	}

	/**
	 * Sanitizes an enrollment status.
	 *
	 * @param mixed $status Raw status.
	 * @return string
	 */
	public static function sanitize_status( $status ) {
		return in_array( $status, array( 'open', 'closed', 'waitlist' ), true ) ? $status : 'open';
	}

	/**
	 * Enrollment URL: the course's own URL, or the global enrollment page
	 * with ?course=<slug>. Not escaped.
	 *
	 * @return string
	 */
	public function enroll_url() {
		$url = (string) get_post_meta( $this->post->ID, self::META_ENROLL_URL, true );
		if ( '' === trim( $url ) ) {
			$base = Settings::get( 'enroll_base_url' );
			$url  = $base ? add_query_arg( 'course', $this->post->post_name, $base ) : get_permalink( $this->post );
		}

		/**
		 * Filters the enrollment URL of a course.
		 *
		 * @param string $url    URL (unescaped).
		 * @param Course $course Course.
		 */
		return (string) apply_filters( 'acme_courses_enroll_url', $url, $this );
	}

	/**
	 * Topics of the course.
	 *
	 * @return \WP_Term[]
	 */
	public function topics() {
		$terms = get_the_terms( $this->post, Post_Types::TAXONOMY );
		return is_array( $terms ) ? $terms : array();
	}
}
