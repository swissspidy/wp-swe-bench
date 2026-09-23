<?php
/**
 * Shared helpers.
 *
 * @package Acme\Support
 */

namespace Acme\Support;

defined( 'ABSPATH' ) || exit;

/**
 * The ticket statuses and their human-readable labels.
 *
 * @return array<string, string>
 */
function ticket_statuses() {
	return array(
		'acme_open'    => __( 'Open', 'acme-support' ),
		'acme_pending' => __( 'Pending', 'acme-support' ),
		'acme_solved'  => __( 'Solved', 'acme-support' ),
		'acme_closed'  => __( 'Closed', 'acme-support' ),
	);
}

/**
 * Priority choices.
 *
 * @return array<string, string>
 */
function ticket_priorities() {
	return array(
		'low'    => __( 'Low', 'acme-support' ),
		'normal' => __( 'Normal', 'acme-support' ),
		'high'   => __( 'High', 'acme-support' ),
		'urgent' => __( 'Urgent', 'acme-support' ),
	);
}

/**
 * Format a MySQL/UTC timestamp as an RFC 3339 string.
 *
 * @param string $mysql_date Date in `Y-m-d H:i:s`.
 * @return string
 */
function to_rfc3339( $mysql_date ) {
	if ( empty( $mysql_date ) || '0000-00-00 00:00:00' === $mysql_date ) {
		return '';
	}
	return mysql_to_rfc3339( $mysql_date );
}
