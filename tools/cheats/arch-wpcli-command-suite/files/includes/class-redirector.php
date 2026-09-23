<?php
/**
 * Front-end redirects.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Performs the redirects on the front end.
 *
 * The matching itself lives in Matcher (shared with `wp acme-redirects test`).
 */
class Redirector {

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Matcher.
	 *
	 * @var Matcher
	 */
	private $matcher;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules   Repository.
	 * @param Matcher|null    $matcher Matcher.
	 */
	public function __construct( Rule_Repository $rules, ?Matcher $matcher = null ) {
		$this->rules   = $rules;
		$this->matcher = $matcher ? $matcher : new Matcher( $rules );
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'maybe_redirect' ), 1 );
	}

	/**
	 * Redirects the current request if a rule matches.
	 */
	public function maybe_redirect() {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! isset( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed and normalized by the matcher.
		$uri    = wp_unslash( $_SERVER['REQUEST_URI'] );
		$result = $this->matcher->match( $uri );
		if ( ! $result ) {
			return;
		}

		$this->rules->record_hit( $result['rule']->id );

		if ( ! $result['rule']->is_redirect() ) {
			nocache_headers();
			wp_die(
				esc_html__( 'This content has been removed.', 'acme-redirects' ),
				esc_html__( 'Gone', 'acme-redirects' ),
				array( 'response' => 410 )
			);
		}

		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- targets may be external by design.
		wp_redirect( $result['target'], $result['status'], 'Acme Redirects' );
		exit;
	}
}
