<?php
/**
 * Stories list table: approval column, "Approve" row action and its handler.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom\Admin;

use Acme\Newsroom\Approval;
use Acme\Newsroom\Story_Post_Type;
use function Acme\Newsroom\get_approval;
use function Acme\Newsroom\is_approved;

defined( 'ABSPATH' ) || exit;

/**
 * List table tweaks.
 */
class Stories_List {

	const ACTION = 'acme_newsroom_approve';

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'manage_' . Story_Post_Type::POST_TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . Story_Post_Type::POST_TYPE . '_posts_custom_column', array( $this, 'column_content' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'row_actions' ), 10, 2 );
		add_action( 'admin_post_' . self::ACTION, array( $this, 'handle_approve' ) );
		add_action( 'admin_notices', array( $this, 'notice' ) );
	}

	/**
	 * Adds the "Approval" column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function columns( $columns ) {
		$columns['acme_approval'] = __( 'Approval', 'acme-newsroom' );
		return $columns;
	}

	/**
	 * Column content.
	 *
	 * @param string $column  Column.
	 * @param int    $post_id Story ID.
	 */
	public function column_content( $column, $post_id ) {
		if ( 'acme_approval' !== $column ) {
			return;
		}
		$approval = get_approval( $post_id );
		if ( ! $approval['approved'] ) {
			echo '<span class="acme-approval acme-approval--pending">' . esc_html__( 'Not approved', 'acme-newsroom' ) . '</span>';
			return;
		}
		$user = get_userdata( $approval['approved_by'] );
		printf(
			'<span class="acme-approval acme-approval--approved">%s</span>',
			esc_html(
				$user
					/* translators: %s: display name */
					? sprintf( __( 'Approved by %s', 'acme-newsroom' ), $user->display_name )
					: __( 'Approved', 'acme-newsroom' )
			)
		);
	}

	/**
	 * URL of the "Approve" action.
	 *
	 * @param int $story_id Story ID.
	 * @return string
	 */
	public static function approve_url( $story_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action' => self::ACTION,
					'story'  => (int) $story_id,
				),
				admin_url( 'admin-post.php' )
			),
			self::ACTION . '_' . (int) $story_id
		);
	}

	/**
	 * "Approve" row action.
	 *
	 * @param array    $actions Actions.
	 * @param \WP_Post $post    Post.
	 * @return array
	 */
	public function row_actions( $actions, $post ) {
		if ( Story_Post_Type::POST_TYPE !== $post->post_type || is_approved( $post ) || 'publish' === $post->post_status ) {
			return $actions;
		}
		if ( Approval::current_user_can_approve( $post->ID ) ) {
			$actions['acme_approve'] = sprintf(
				'<a href="%s" class="acme-approve-link" aria-label="%s">%s</a>',
				esc_url( self::approve_url( $post->ID ) ),
				/* translators: %s: story title */
				esc_attr( sprintf( __( 'Approve “%s”', 'acme-newsroom' ), get_the_title( $post ) ) ),
				esc_html__( 'Approve', 'acme-newsroom' )
			);
		}
		return $actions;
	}

	/**
	 * admin-post.php?action=acme_newsroom_approve&story=ID
	 */
	public function handle_approve() {
		$story_id = isset( $_GET['story'] ) ? absint( $_GET['story'] ) : 0;
		check_admin_referer( self::ACTION . '_' . $story_id );

		$story = get_post( $story_id );
		if ( ! $story || Story_Post_Type::POST_TYPE !== $story->post_type ) {
			wp_die( esc_html__( 'Invalid story.', 'acme-newsroom' ), 404 );
		}
		if ( ! Approval::current_user_can_approve( $story_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to approve this story.', 'acme-newsroom' ), 403 );
		}

		Approval::approve( $story_id, get_current_user_id() );

		wp_safe_redirect(
			add_query_arg(
				array(
					'post_type'           => Story_Post_Type::POST_TYPE,
					'acme_approved_story' => $story_id,
				),
				admin_url( 'edit.php' )
			)
		);
		exit;
	}

	/**
	 * Confirmation notice.
	 */
	public function notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['acme_approved_story'] ) ) {
			return;
		}
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Story approved.', 'acme-newsroom' ) . '</p></div>';
	}
}
