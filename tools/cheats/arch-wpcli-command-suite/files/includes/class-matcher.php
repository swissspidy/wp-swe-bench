<?php
/**
 * Rule matching, shared by the front end and WP-CLI.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * Finds the rule that applies to a request URI and resolves its target.
 *
 * Matching rules (documented in readme.txt, "How rules are matched"):
 *
 * 1. Enabled rules are checked in order of priority (lowest first), then by ID.
 *    The first matching rule wins.
 * 2. Paths are compared percent-decoded, case-insensitively, with duplicate
 *    slashes collapsed and ignoring a trailing slash.
 * 3. exact:  the path equals the source. If the source has a query string,
 *            the request must have exactly those query arguments (any order).
 * 4. prefix: the path starts with the source ("/shop" matches "/shop" and
 *            "/shop/anything", but not "/shopping"). A target ending in "*"
 *            gets the rest of the path appended.
 * 5. regex:  the source (no delimiters, case-insensitive) is matched against the
 *            decoded path including its trailing slash; $1..$9 in the target are
 *            replaced with the captured groups.
 * 6. The request's query string is appended to the target, unless the target
 *    has a query string of its own or the rule matched on a query string.
 * 7. Relative targets are made absolute with home_url().
 * 8. `acme_redirects_match` can veto a match, `acme_redirects_target` can change
 *    the final target.
 */
class Matcher {

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules Repository.
	 */
	public function __construct( Rule_Repository $rules ) {
		$this->rules = $rules;
	}

	/**
	 * Finds the rule for a request.
	 *
	 * @param string $uri Request URI (path + optional query string) or a full URL on this site.
	 * @return array{rule: Rule, status: int, target: string, path: string}|null
	 *         `target` is the final absolute URL ('' for 410 rules).
	 */
	public function match( $uri ) {
		$raw_path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		$query    = (string) wp_parse_url( $uri, PHP_URL_QUERY );

		// Installs in a sub-directory: match relative to the home path.
		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		if ( '/' !== $home_path && 0 === stripos( $raw_path, untrailingslashit( $home_path ) ) ) {
			$raw_path = substr( $raw_path, strlen( untrailingslashit( $home_path ) ) );
		}

		$path         = normalize_path( $raw_path );
		$decoded_path = preg_replace( '#/{2,}#', '/', '/' . ltrim( rawurldecode( $raw_path ), '/' ) );
		$query_args   = parse_query( $query );

		foreach ( $this->rules->get_enabled_rules() as $rule ) {
			$is_match = false;
			$groups   = array();
			$rest     = '';
			$on_query = false;

			switch ( $rule->match_type ) {
				case 'regex':
					$is_match = (bool) preg_match( regex_for( $rule->source ), $decoded_path, $groups );
					break;

				case 'prefix':
					$prefix = normalize_path( $rule->source );
					if ( '/' === $prefix ) {
						$is_match = true;
						$rest     = ltrim( $path, '/' );
					} elseif ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
						$is_match = true;
						$rest     = ltrim( (string) substr( $path, strlen( $prefix ) ), '/' );
					}
					break;

				case 'exact':
				default:
					$source_path  = normalize_path( (string) wp_parse_url( $rule->source, PHP_URL_PATH ) );
					$source_query = (string) wp_parse_url( $rule->source, PHP_URL_QUERY );
					if ( $source_path === $path ) {
						if ( '' === $source_query ) {
							$is_match = true;
						} elseif ( parse_query( $source_query ) == $query_args ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- order-insensitive array comparison.
							$is_match = true;
							$on_query = true;
						}
					}
					break;
			}

			/**
			 * Filters whether a rule matches the current request.
			 *
			 * @param bool   $is_match Whether the rule matches.
			 * @param Rule   $rule     The rule.
			 * @param string $path     Normalized request path.
			 * @param array  $query    Request query arguments.
			 */
			if ( ! apply_filters( 'acme_redirects_match', $is_match, $rule, $path, $query_args ) ) {
				continue;
			}

			return array(
				'rule'   => $rule,
				'status' => $rule->status,
				'target' => $rule->is_redirect() ? $this->resolve_target( $rule, $groups, $rest, $query, $on_query, $path ) : '',
				'path'   => $path,
			);
		}

		return null;
	}

	/**
	 * Resolves the final target URL of a matched redirect rule.
	 *
	 * @param Rule   $rule     Rule.
	 * @param array  $groups   Regex captures.
	 * @param string $rest     Remainder of the path (prefix rules).
	 * @param string $query    Raw request query string.
	 * @param bool   $on_query Whether the rule matched on the query string.
	 * @param string $path     Normalized path.
	 * @return string
	 */
	private function resolve_target( Rule $rule, array $groups, $rest, $query, $on_query, $path ) {
		$target = $rule->target;
		if ( 'regex' === $rule->match_type ) {
			$target = preg_replace_callback(
				'/\$(\d)/',
				static function ( $m ) use ( $groups ) {
					return isset( $groups[ (int) $m[1] ] ) ? $groups[ (int) $m[1] ] : '';
				},
				$target
			);
		} elseif ( 'prefix' === $rule->match_type && '*' === substr( $target, -1 ) ) {
			$target = substr( $target, 0, -1 ) . $rest;
		}

		if ( '' !== $query && ! $on_query && false === strpos( $target, '?' ) ) {
			$target .= '?' . $query;
		}
		if ( '' !== $target && '/' === $target[0] ) {
			$target = home_url( $target );
		}

		/**
		 * Filters the redirect target.
		 *
		 * @param string $target Absolute target URL.
		 * @param Rule   $rule   The matched rule.
		 * @param string $path   Normalized request path.
		 */
		return (string) apply_filters( 'acme_redirects_target', $target, $rule, $path );
	}
}
