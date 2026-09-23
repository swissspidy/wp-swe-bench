<?php
/**
 * Freelancers.
 *
 * Freelancers are Contributors flagged with the `acme_freelancer` user meta.
 * They need a few things Contributors can't do, and must not do a few
 * things Contributors can. This class bends the Contributor role accordingly.
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Freelancer tweaks.
 */
class Freelancers {

	const META = 'acme_freelancer';

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'user_has_cap', array( $this, 'grant_uploads' ), 10, 4 );
		add_filter( 'map_meta_cap', array( $this, 'lock_approved_stories' ), 10, 4 );
		add_action( 'admin_menu', array( $this, 'hide_posts_menu' ), 99 );
		add_action( 'pre_get_posts', array( $this, 'only_own_stories' ) );

		add_action( 'show_user_profile', array( $this, 'profile_field' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_field' ) );
		add_action( 'personal_options_update', array( $this, 'save_profile_field' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_profile_field' ) );
		add_filter( 'manage_users_columns', array( $this, 'users_column' ) );
		add_filter( 'manage_users_custom_column', array( $this, 'users_column_content' ), 10, 3 );
	}

	/**
	 * Freelancers upload their own photos.
	 *
	 * @param array    $allcaps All caps of the user.
	 * @param array    $caps    Required primitive caps.
	 * @param array    $args    Arguments.
	 * @param \WP_User $user    User.
	 * @return array
	 */
	public function grant_uploads( $allcaps, $caps, $args, $user ) {
		if ( in_array( 'upload_files', $caps, true ) && is_freelancer( $user->ID ) ) {
			$allcaps['upload_files'] = true;
		}
		return $allcaps;
	}

	/**
	 * Contributors can't edit a story after the desk approved it.
	 *
	 * @param string[] $caps    Required primitive caps.
	 * @param string   $cap     Requested cap.
	 * @param int      $user_id User ID.
	 * @param array    $args    Arguments.
	 * @return string[]
	 */
	public function lock_approved_stories( $caps, $cap, $user_id, $args ) {
		if ( 'edit_post' !== $cap || empty( $args[0] ) ) {
			return $caps;
		}
		$user = get_userdata( $user_id );
		if ( $user && in_array( 'contributor', (array) $user->roles, true ) && is_approved( (int) $args[0] ) ) {
			$caps[] = 'do_not_allow';
		}
		return $caps;
	}

	/**
	 * Freelancers write stories, not blog posts.
	 */
	public function hide_posts_menu() {
		if ( is_freelancer( get_current_user_id() ) ) {
			remove_menu_page( 'edit.php' );
		}
	}

	/**
	 * Freelancers only see their own stories in wp-admin.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function only_own_stories( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || Story_Post_Type::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}
		if ( is_freelancer( get_current_user_id() ) ) {
			$query->set( 'author', get_current_user_id() );
		}
	}

	/**
	 * "Freelancer" checkbox on the profile screen (for user managers).
	 *
	 * @param \WP_User $user User.
	 */
	public function profile_field( $user ) {
		if ( ! current_user_can( 'edit_users' ) ) {
			return;
		}
		wp_nonce_field( 'acme_freelancer_' . $user->ID, '_acme_freelancer_nonce' );
		?>
		<h2><?php esc_html_e( 'Newsroom', 'acme-newsroom' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Freelancer', 'acme-newsroom' ); ?></th>
				<td><label><input type="checkbox" name="acme_freelancer" value="1" <?php checked( is_freelancer( $user->ID ) ); ?> />
				<?php esc_html_e( 'This user is a freelancer (Contributor role required).', 'acme-newsroom' ); ?></label></td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Saves the checkbox.
	 *
	 * @param int $user_id User ID.
	 */
	public function save_profile_field( $user_id ) {
		if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['_acme_freelancer_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_acme_freelancer_nonce'] ), 'acme_freelancer_' . $user_id ) ) {
			return;
		}
		update_user_meta( $user_id, self::META, empty( $_POST['acme_freelancer'] ) ? '0' : '1' );
	}

	/**
	 * Users list column.
	 *
	 * @param array $columns Columns.
	 * @return array
	 */
	public function users_column( $columns ) {
		$columns['acme_freelancer'] = __( 'Freelancer', 'acme-newsroom' );
		return $columns;
	}

	/**
	 * Users list column content.
	 *
	 * @param string $output      Output.
	 * @param string $column_name Column.
	 * @param int    $user_id     User ID.
	 * @return string
	 */
	public function users_column_content( $output, $column_name, $user_id ) {
		if ( 'acme_freelancer' === $column_name ) {
			return is_freelancer( $user_id ) ? esc_html__( 'Yes', 'acme-newsroom' ) : '—';
		}
		return $output;
	}
}
