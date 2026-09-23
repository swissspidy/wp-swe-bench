<?php
/**
 * Input validation for the REST endpoints.
 *
 * All rules live in the route argument definitions (validate_callback / sanitize_callback)
 * so that they run before any callback does. That is what makes batch requests with
 * `"validation": "require-all-validate"` reject invalid operations without writing anything.
 *
 * @package Acme\Tasks
 */

namespace Acme\Tasks;

use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Validators and argument definitions.
 */
class Validation {

	/**
	 * Build a 400 error.
	 *
	 * @param string $code    Error code.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	private static function invalid( $code, $message ) {
		return new WP_Error( $code, $message, array( 'status' => 400 ) );
	}

	/**
	 * Is the value "not given" in the 1.x sense (empty string from form posts)?
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	public static function is_blank( $value ) {
		return null === $value || '' === $value;
	}

	/**
	 * Clean a title.
	 *
	 * @param mixed $title Raw title.
	 * @return string
	 */
	public static function clean_title( $title ) {
		return is_scalar( $title ) ? trim( sanitize_text_field( (string) $title ) ) : '';
	}

	/**
	 * Title of a task or list: non-empty after cleaning, at most MAX_TITLE_LEN characters.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function title( $value ) {
		$title = self::clean_title( $value );
		if ( '' === $title ) {
			return self::invalid( 'acme_tasks_invalid_title', __( 'A title is required.', 'acme-tasks' ) );
		}
		if ( mb_strlen( $title ) > MAX_TITLE_LEN ) {
			return self::invalid( 'acme_tasks_invalid_title', __( 'The title is too long.', 'acme-tasks' ) );
		}
		return true;
	}

	/**
	 * Notes: any text.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function notes( $value ) {
		return is_scalar( $value ) ? true : self::invalid( 'acme_tasks_invalid_notes', __( 'Notes must be text.', 'acme-tasks' ) );
	}

	/**
	 * Status: open|done.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function status( $value ) {
		return in_array( $value, TASK_STATUSES, true ) ? true : self::invalid( 'acme_tasks_invalid_status', __( 'Status must be "open" or "done".', 'acme-tasks' ) );
	}

	/**
	 * Legacy `completed` flag / `archived`: boolean-ish.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function boolean( $value ) {
		return null !== parse_bool( $value ) ? true : self::invalid( 'acme_tasks_invalid_boolean', __( 'A boolean value is required.', 'acme-tasks' ) );
	}

	/**
	 * Due date: empty (clears it) or a real date in YYYY-MM-DD.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function due_date( $value ) {
		if ( self::is_blank( $value ) || is_valid_date( $value ) ) {
			return true;
		}
		return self::invalid( 'acme_tasks_invalid_due_date', __( 'Due dates must be valid dates in YYYY-MM-DD format.', 'acme-tasks' ) );
	}

	/**
	 * Position: empty (append / unchanged) or a non-negative integer.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function position( $value ) {
		if ( self::is_blank( $value ) || is_non_negative_int( $value ) ) {
			return true;
		}
		return self::invalid( 'acme_tasks_invalid_position', __( 'Position must be a non-negative integer.', 'acme-tasks' ) );
	}

	/**
	 * Assignee: 0/empty (nobody) or a user who has access to the list.
	 *
	 * @param mixed    $value   Value.
	 * @param int|null $list_id List the task belongs to (null when unknown: checked later).
	 * @return true|WP_Error
	 */
	public static function assignee( $value, $list_id ) {
		if ( self::is_blank( $value ) || 0 === $value || '0' === $value ) {
			return true;
		}
		if ( ! is_non_negative_int( $value ) ) {
			return self::invalid( 'acme_tasks_invalid_assignee', __( 'The assignee must be a user ID.', 'acme-tasks' ) );
		}
		if ( null !== $list_id && ! can_be_assigned( (int) $value, $list_id ) ) {
			return self::invalid( 'acme_tasks_invalid_assignee', __( 'Tasks can only be assigned to people who have access to the list.', 'acme-tasks' ) );
		}
		return true;
	}

	/**
	 * Color: #rrggbb.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function color( $value ) {
		return is_valid_color( $value ) ? true : self::invalid( 'acme_tasks_invalid_color', __( 'Colors must be given as #rrggbb.', 'acme-tasks' ) );
	}

	/**
	 * Members: list of IDs of users who can use task lists.
	 *
	 * @param mixed $value Value.
	 * @return true|WP_Error
	 */
	public static function members( $value ) {
		if ( ! is_array( $value ) ) {
			return self::invalid( 'acme_tasks_invalid_member', __( 'Members must be a list of user IDs.', 'acme-tasks' ) );
		}
		foreach ( $value as $member ) {
			$user = is_non_negative_int( $member ) ? get_userdata( (int) $member ) : false;
			if ( ! $user || ! user_can( $user, 'edit_posts' ) ) {
				return self::invalid( 'acme_tasks_invalid_member', __( 'Members must be users who can use task lists.', 'acme-tasks' ) );
			}
		}
		return true;
	}

	/**
	 * Wrap a single-value validator as a validate_callback.
	 *
	 * @param callable $validator Validator taking the value.
	 * @return callable
	 */
	private static function cb( callable $validator ) {
		return static function ( $value ) use ( $validator ) {
			return $validator( $value );
		};
	}

	/**
	 * Argument definitions for creating/updating a task.
	 *
	 * @param bool $creating Whether the title is required (create) or optional (update).
	 * @return array
	 */
	public static function task_args( $creating ) {
		$list_of_request = static function ( WP_REST_Request $request ) use ( $creating ) {
			if ( $creating ) {
				return (int) $request['list_id'];
			}
			$task = Task_Repository::get( (int) $request['id'] );
			// Unknown task: the permission check answers with a 404.
			return $task ? $task['list_id'] : null;
		};

		return array(
			'title'     => array(
				'description'       => __( 'Title of the task.', 'acme-tasks' ),
				'required'          => $creating,
				'validate_callback' => self::cb( array( __CLASS__, 'title' ) ),
				'sanitize_callback' => array( __CLASS__, 'clean_title' ),
			),
			'notes'     => array(
				'description'       => __( 'Notes (plain text).', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'notes' ) ),
				'sanitize_callback' => static function ( $value ) {
					return sanitize_textarea_field( (string) $value );
				},
			),
			'status'    => array(
				'description'       => __( 'open or done.', 'acme-tasks' ),
				'enum'              => TASK_STATUSES,
				'validate_callback' => self::cb( array( __CLASS__, 'status' ) ),
			),
			'completed' => array(
				'description'       => __( 'Deprecated: use status.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'boolean' ) ),
			),
			'due_date'  => array(
				'description'       => __( 'Due date (YYYY-MM-DD); empty to clear.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'due_date' ) ),
			),
			'assignee'  => array(
				'description'       => __( 'User ID of the assignee; 0 for nobody.', 'acme-tasks' ),
				'validate_callback' => static function ( $value, $request ) use ( $list_of_request ) {
					return self::assignee( $value, $list_of_request( $request ) );
				},
			),
			'position'  => array(
				'description'       => __( '0-based position within the list.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'position' ) ),
			),
		);
	}

	/**
	 * Argument definitions for creating/updating a list.
	 *
	 * @param bool $creating Whether the title is required.
	 * @return array
	 */
	public static function list_args( $creating ) {
		return array(
			'title'    => array(
				'description'       => __( 'Title of the list.', 'acme-tasks' ),
				'required'          => $creating,
				'validate_callback' => self::cb( array( __CLASS__, 'title' ) ),
				'sanitize_callback' => array( __CLASS__, 'clean_title' ),
			),
			'color'    => array(
				'description'       => __( 'Color as #rrggbb.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'color' ) ),
				'sanitize_callback' => __NAMESPACE__ . '\\sanitize_color',
			),
			'members'  => array(
				'description'       => __( 'User IDs of the members.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'members' ) ),
			),
			'archived' => array(
				'description'       => __( 'Whether the list is archived.', 'acme-tasks' ),
				'validate_callback' => self::cb( array( __CLASS__, 'boolean' ) ),
			),
		);
	}
}
