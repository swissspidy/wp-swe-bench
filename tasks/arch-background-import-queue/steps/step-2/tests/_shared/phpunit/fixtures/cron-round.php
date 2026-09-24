<?php
/**
 * `wp eval-file cron-round.php [ahead]`: runs the non-core cron events that are due
 * (within `ahead` seconds) once, like one WP-Cron request would.
 */
$ahead = isset( $args[0] ) ? (int) $args[0] : 0;
$ran   = 0;
foreach ( (array) _get_cron_array() as $ts => $hooks ) {
	if ( $ts > time() + $ahead ) {
		continue;
	}
	foreach ( $hooks as $hook => $events ) {
		if ( 0 === strpos( $hook, 'wp_' ) || in_array( $hook, array( 'delete_expired_transients', 'recovery_mode_clean_expired_keys', 'importer_scheduled_cleanup', 'upgrader_scheduled_cleanup' ), true ) ) {
			continue; // Core housekeeping.
		}
		foreach ( $events as $event ) {
			if ( ! empty( $event['schedule'] ) ) {
				wp_reschedule_event( $ts, $event['schedule'], $hook, $event['args'] );
			}
			wp_unschedule_event( $ts, $hook, $event['args'] );
			do_action_ref_array( $hook, $event['args'] );
			++$ran;
		}
	}
}
echo "ran $ran\n";
