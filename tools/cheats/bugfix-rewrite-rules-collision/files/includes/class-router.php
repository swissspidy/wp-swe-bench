<?php
/**
 * Routing of /docs/ URLs.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves everything under `/docs/` with the site's actual content instead of pattern matching
 * alone, because the same URL shape can mean different things:
 *
 * - `/docs/`                                  the "docs" page if there is one, otherwise the docs archive
 * - `/docs/{product}/`, `/docs/{product}/page/{n}/`   product archive
 * - `/docs/{product}/[{version}/]{path}/[{n}/]`      a doc of that product (and version)
 * - `/docs/{anything else}/`                  a regular page below the "docs" page, if it exists
 *
 * Only two rewrite rules are needed and they never change with the content, so adding products,
 * versions or docs never requires the rules to be regenerated.
 */
class Router {

	const QUERY_VAR = 'acme_docs_route';

	/**
	 * Bump when the rules below change: they are regenerated once after deploying.
	 */
	const RULES_VERSION = '2';

	const RULES_OPTION = 'acme_docs_rules_version';

	/**
	 * Marker for `/docs/` itself.
	 */
	const INDEX = '__index__';

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rules' ), 20 );
		add_action( 'init', array( $this, 'maybe_flush_rules' ), 99 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'request' ) );
	}

	/**
	 * Rewrite rules.
	 */
	public function add_rules() {
		add_rewrite_rule( '^docs/?$', 'index.php?' . self::QUERY_VAR . '=' . self::INDEX, 'top' );
		add_rewrite_rule( '^docs/(.+?)/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * Regenerates the rewrite rules once when they changed (after an update), not on every request.
	 */
	public function maybe_flush_rules() {
		if ( get_option( self::RULES_OPTION ) !== self::RULES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( self::RULES_OPTION, self::RULES_VERSION );
		}
	}

	/**
	 * Query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Turns a docs route into the query vars of what it points to.
	 *
	 * @param array $vars Request query vars.
	 * @return array
	 */
	public function request( $vars ) {
		if ( ! isset( $vars[ self::QUERY_VAR ] ) ) {
			return $vars;
		}
		$route = (string) $vars[ self::QUERY_VAR ];
		unset( $vars[ self::QUERY_VAR ] );

		$resolved = self::resolve( $route );

		/**
		 * Filters the query vars a `/docs/…` URL resolves to.
		 *
		 * @since 2.0.0
		 *
		 * @param array  $resolved Query vars (`array( 'error' => '404' )` when nothing matches).
		 * @param string $route    Requested path below `/docs/`.
		 */
		$resolved = apply_filters( 'acme_docs_resolve_route', $resolved, $route );

		return array_merge( $vars, $resolved );
	}

	/**
	 * Resolves a route.
	 *
	 * @param string $route Path below `/docs/` (or the index marker).
	 * @return array Query vars.
	 */
	public static function resolve( $route ) {
		$not_found = array( 'error' => '404' );

		if ( self::INDEX === $route ) {
			return self::landing_page() ? array( 'pagename' => 'docs' ) : array( 'post_type' => Post_Types::DOC );
		}

		$segments = array_values( array_filter( explode( '/', trim( $route, '/' ) ), 'strlen' ) );
		if ( ! $segments ) {
			return $not_found;
		}
		$segments = array_map( 'sanitize_title_for_query', array_map( 'rawurldecode', $segments ) );

		$product = get_term_by( 'slug', $segments[0], Post_Types::PRODUCT );
		if ( ! $product ) {
			// Docs archive pagination when there is no landing page.
			if ( 2 === count( $segments ) && 'page' === $segments[0] && ctype_digit( $segments[1] ) && ! self::landing_page() ) {
				return array(
					'post_type' => Post_Types::DOC,
					'paged'     => (int) $segments[1],
				);
			}
			// A page below the docs landing page.
			$page = get_page_by_path( 'docs/' . implode( '/', $segments ) );
			if ( $page ) {
				return array( 'pagename' => 'docs/' . implode( '/', $segments ) );
			}
			return $not_found;
		}

		$rest = array_slice( $segments, 1 );

		// Product archive.
		if ( ! $rest ) {
			return array( Post_Types::PRODUCT => $product->slug );
		}
		if ( 2 === count( $rest ) && 'page' === $rest[0] && ctype_digit( $rest[1] ) ) {
			return array(
				Post_Types::PRODUCT => $product->slug,
				'paged'             => (int) $rest[1],
			);
		}
		if ( 'feed' === $rest[0] && count( $rest ) <= 2 ) {
			return array(
				Post_Types::PRODUCT => $product->slug,
				'feed'              => isset( $rest[1] ) ? $rest[1] : 'feed',
			);
		}

		// Version.
		$version = '';
		if ( preg_match( '/^v[0-9]+$/', $rest[0] ) && term_exists( $rest[0], Post_Types::VERSION ) ) {
			$version = $rest[0];
			$rest    = array_slice( $rest, 1 );
			if ( ! $rest ) {
				return $not_found;
			}
		}

		$extra = array();
		if ( count( $rest ) > 1 && 'embed' === end( $rest ) ) {
			array_pop( $rest );
			$extra['embed'] = true;
		}

		$doc = acme_docs_get_doc_by_path( $product->slug, implode( '/', $rest ), $version, array( 'publish', 'private' ) );

		// Multi-page docs: `…/doc/2/`.
		if ( ! $doc && count( $rest ) > 1 && ctype_digit( end( $rest ) ) ) {
			$page_number = (int) array_pop( $rest );
			$doc         = acme_docs_get_doc_by_path( $product->slug, implode( '/', $rest ), $version, array( 'publish', 'private' ) );
			if ( $doc ) {
				$extra['page'] = $page_number;
			}
		}

		if ( ! $doc ) {
			return $not_found;
		}

		return array_merge(
			array(
				'post_type' => Post_Types::DOC,
				'p'         => $doc->ID,
			),
			$extra
		);
	}

	/**
	 * The published "docs" landing page, if any.
	 *
	 * @return \WP_Post|null
	 */
	protected static function landing_page() {
		$page = get_page_by_path( 'docs' );
		return $page && 'publish' === $page->post_status ? $page : null;
	}
}
