<?php
/**
 * WP-CLI: `wp acme-stats …`
 *
 * @package Acme\Stats
 */

namespace Acme\Stats;

defined( 'ABSPATH' ) || exit;

/**
 * Newsroom numbers on the command line.
 */
class CLI {

	/**
	 * Print the stats of the site or of one author.
	 *
	 * ## OPTIONS
	 *
	 * [--author=<author>]
	 * : Author ID or login. Default: the whole site.
	 *
	 * [--format=<format>]
	 * : `json` or `table` (totals only).
	 * ---
	 * default: table
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-stats show --author=bruno --format=json
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function show( $args, $assoc_args ) {
		$scope = Scope::site();
		if ( ! empty( $assoc_args['author'] ) ) {
			$user = self::user( $assoc_args['author'] );
			$scope = Scope::author( $user->ID );
		}
		$stats = Plugin::instance()->stats->get( $scope );

		if ( 'json' === \WP_CLI\Utils\get_flag_value( $assoc_args, 'format', 'table' ) ) {
			\WP_CLI::line( (string) wp_json_encode( $stats, JSON_PRETTY_PRINT ) );
			return;
		}
		\WP_CLI\Utils\format_items(
			'table',
			array(
				array(
					'scope'    => $stats['scope'],
					'posts'    => $stats['posts']['total'],
					'words'    => $stats['words']['total'],
					'comments' => $stats['comments']['approved'],
					'pending'  => $stats['comments']['pending'],
				),
			),
			array( 'scope', 'posts', 'words', 'comments', 'pending' )
		);
	}

	/**
	 * Resolve a user by ID or login.
	 *
	 * @param string $value ID or login.
	 * @return \WP_User
	 */
	protected static function user( $value ) {
		$user = is_numeric( $value ) ? get_user_by( 'id', (int) $value ) : get_user_by( 'login', $value );
		if ( ! $user ) {
			\WP_CLI::error( sprintf( 'User %s not found.', $value ) );
		}
		return $user;
	}
}
