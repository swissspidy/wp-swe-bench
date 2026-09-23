<?php
/**
 * REST additions (used by the mobile app).
 *
 * @package Acme\Events
 */

namespace Acme\Events;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a public, read-only `acme_event` field to /wp/v2/events.
 */
class Rest {

	/**
	 * Register hooks.
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Register the field.
	 */
	public function register_fields() {
		register_rest_field(
			POST_TYPE,
			'acme_event',
			array(
				'get_callback' => array( $this, 'get_field' ),
				'schema'       => array(
					'description' => __( 'Event details.', 'acme-events-lite' ),
					'type'        => 'object',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
					'properties'  => array(
						'start'      => array( 'type' => 'string' ),
						'end'        => array( 'type' => 'string' ),
						'all_day'    => array( 'type' => 'boolean' ),
						'status'     => array( 'type' => 'string' ),
						'venue'      => array( 'type' => 'string' ),
						'date_label' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	/**
	 * Field value.
	 *
	 * @param array $data Prepared post data.
	 * @return array|null
	 */
	public function get_field( $data ) {
		$event = get_event( $data['id'] );
		if ( ! $event ) {
			return null;
		}
		return array(
			'start'      => $event->get_start() ? wp_date( 'c', $event->get_start() ) : '',
			'end'        => $event->get_end() ? wp_date( 'c', $event->get_end() ) : '',
			'all_day'    => $event->is_all_day(),
			'status'     => $event->get_status(),
			'venue'      => $event->get_venue(),
			'date_label' => format_event_date( $event->get_post() ),
		);
	}
}
