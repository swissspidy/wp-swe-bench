<?php
/**
 * Plugin Name: Acme assignment mails
 * Description: Emails people when a task is assigned to them (site glue code, maintained by IT).
 */

add_action(
	'acme_tasks_task_created',
	static function ( $task ) {
		if ( ! empty( $task['assignee_id'] ) && (int) $task['assignee_id'] !== get_current_user_id() ) {
			acme_it_send_assignment_mail( $task );
		}
	}
);

add_action(
	'acme_tasks_task_updated',
	static function ( $task, $previous ) {
		if ( ! empty( $task['assignee_id'] ) && (int) $task['assignee_id'] !== (int) $previous['assignee_id'] && (int) $task['assignee_id'] !== get_current_user_id() ) {
			acme_it_send_assignment_mail( $task );
		}
	},
	10,
	2
);

/**
 * Send the "you were assigned" mail.
 *
 * @param array $task Task row.
 */
function acme_it_send_assignment_mail( array $task ) {
	$user = get_userdata( (int) $task['assignee_id'] );
	if ( ! $user ) {
		return;
	}
	wp_mail(
		$user->user_email,
		sprintf( '[Acme Tasks] Assigned to you: %s', $task['title'] ),
		sprintf( "You were assigned the task \"%s\" (task #%d, list #%d).\n", $task['title'], $task['id'], $task['list_id'] )
	);
}
