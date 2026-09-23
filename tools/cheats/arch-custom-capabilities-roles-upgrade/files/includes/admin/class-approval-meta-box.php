<?php
/**
 * "Approval" box on the story edit screen.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom\Admin;

use Acme\Newsroom\Approval;
use Acme\Newsroom\Story_Post_Type;
use function Acme\Newsroom\get_approval;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the approval state and an "Approve" button for the desk.
 */
class Approval_Meta_Box {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'add_meta_boxes_' . Story_Post_Type::POST_TYPE, array( $this, 'add' ) );
	}

	/**
	 * Adds the box.
	 */
	public function add() {
		add_meta_box( 'acme-approval', __( 'Approval', 'acme-newsroom' ), array( $this, 'render' ), Story_Post_Type::POST_TYPE, 'side', 'high' );
	}

	/**
	 * Box content.
	 *
	 * @param \WP_Post $post Story.
	 */
	public function render( $post ) {
		$approval = get_approval( $post );
		if ( $approval['approved'] ) {
			$user = get_userdata( $approval['approved_by'] );
			echo '<p>' . esc_html(
				$user
					/* translators: %s: display name */
					? sprintf( __( 'Approved by %s.', 'acme-newsroom' ), $user->display_name )
					: __( 'Approved.', 'acme-newsroom' )
			) . '</p>';
			return;
		}
		echo '<p>' . esc_html__( 'Not approved yet.', 'acme-newsroom' ) . '</p>';
		if ( 'auto-draft' !== $post->post_status && Approval::current_user_can_approve( $post->ID ) ) {
			printf( '<a class="button" href="%s">%s</a>', esc_url( Stories_List::approve_url( $post->ID ) ), esc_html__( 'Approve', 'acme-newsroom' ) );
		}
	}
}
