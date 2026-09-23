<?php
/**
 * Dashboard → Content stats.
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Admin page.
 */
class Admin_Page {

	/** @var Stats */
	private $stats;

	/**
	 * Constructor.
	 *
	 * @param Stats $stats Stats.
	 */
	public function __construct( Stats $stats ) {
		$this->stats = $stats;
	}

	/**
	 * Hooks.
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add' ) );
	}

	/**
	 * Menu entry.
	 */
	public function add() {
		add_dashboard_page( __( 'Content stats', 'acme-dashboard-stats' ), __( 'Content stats', 'acme-dashboard-stats' ), 'edit_posts', 'acme-stats', array( $this, 'render' ) );
	}

	/**
	 * Page.
	 */
	public function render() {
		$user_id = get_current_user_id();
		$scope   = Scope::for_user( $user_id );

		// Editors can look at a single author.
		if ( isset( $_GET['author'] ) && current_user_can( 'edit_others_posts' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$author = absint( $_GET['author'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$scope  = $author ? Scope::author( $author ) : Scope::site();
		}
		if ( ! $scope->visible_to( $user_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to see these numbers.', 'acme-dashboard-stats' ), 403 );
		}
		$stats = $this->stats->get( $scope );
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Content stats', 'acme-dashboard-stats' ); ?></h1>
			<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
				<form method="get">
					<input type="hidden" name="page" value="acme-stats" />
					<?php
					wp_dropdown_users(
						array(
							'name'              => 'author',
							'capability'        => array( 'edit_posts' ),
							'show_option_none'  => __( 'Whole site', 'acme-dashboard-stats' ),
							'option_none_value' => 0,
							'selected'          => $scope->author_id,
						)
					);
					submit_button( __( 'Show', 'acme-dashboard-stats' ), 'secondary', '', false );
					?>
				</form>
			<?php endif; ?>
			<?php echo Format::report( $stats ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in Format. ?>
		</div>
		<?php
	}
}
