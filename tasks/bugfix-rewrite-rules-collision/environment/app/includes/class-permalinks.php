<?php
/**
 * Doc URLs.
 *
 * @package Acme\Docs
 */

namespace Acme\Docs;

defined( 'ABSPATH' ) || exit;

/**
 * URL scheme:
 *
 * - `/docs/{product}/`                       product page (list of the product's docs), paginated `/page/2/`
 * - `/docs/{product}/{parent}/{doc}/`        a doc (any depth), multi-page docs `/2/`
 * - `/docs/{product}/{version}/{parent}/{doc}/` a doc of another major version (`v1`, `v2`, …)
 *
 * Slugs only have to be unique among siblings of the same product and version, so
 * `/docs/acme-cloud/getting-started/installation/` and `/docs/acme-cli/getting-started/installation/`
 * can coexist.
 */
class Permalinks {

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'init', array( $this, 'add_rules' ), 20 );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'request' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 4 );
		add_filter( 'preview_post_link', array( $this, 'preview_post_link' ), 10, 2 );
		add_filter( 'wp_unique_post_slug', array( $this, 'unique_slug' ), 10, 6 );
	}

	/**
	 * Rules for versioned docs.
	 */
	public function add_rules() {
		add_rewrite_rule(
			'^docs/([^/]+)/(v[0-9]+)/(.+?)/?$',
			'index.php?product=$matches[1]&doc_version=$matches[2]&doc=$matches[3]',
			'top'
		);
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Query vars.
	 * @return string[]
	 */
	public function query_vars( $vars ) {
		$vars[] = Post_Types::VERSION;
		return $vars;
	}

	/**
	 * Single docs are not product archives.
	 *
	 * @param array $vars Request query vars.
	 * @return array
	 */
	public function request( $vars ) {
		if ( ! empty( $vars[ Post_Types::DOC ] ) && isset( $vars[ Post_Types::PRODUCT ] ) ) {
			// The product is in the URL for readability. Querying by it would turn the doc into a
			// taxonomy archive (wrong template, no comments, …).
			unset( $vars[ Post_Types::PRODUCT ] );
		}
		return $vars;
	}

	/**
	 * Restricts versioned requests to the requested version.
	 *
	 * @param \WP_Query $query Query.
	 */
	public function pre_get_posts( $query ) {
		if ( is_admin() || ! $query->is_main_query() ) {
			return;
		}
		$version = $query->get( Post_Types::VERSION );
		if ( $version ) {
			$tax_query   = (array) $query->get( 'tax_query' );
			$tax_query[] = array(
				'taxonomy' => Post_Types::VERSION,
				'field'    => 'slug',
				'terms'    => sanitize_title( $version ),
			);
			$query->set( 'tax_query', $tax_query );
		}
	}

	/**
	 * Product term of a doc.
	 *
	 * @param \WP_Post $post Doc.
	 * @return \WP_Term|null
	 */
	public static function get_product( $post ) {
		$terms = get_the_terms( $post, Post_Types::PRODUCT );
		return is_array( $terms ) && $terms ? reset( $terms ) : null;
	}

	/**
	 * Version slug of a doc ('' for current docs).
	 *
	 * @param \WP_Post $post Doc.
	 * @return string
	 */
	public static function get_version( $post ) {
		$terms = get_the_terms( $post, Post_Types::VERSION );
		return is_array( $terms ) && $terms ? reset( $terms )->slug : '';
	}

	/**
	 * Pretty URL of a doc.
	 *
	 * @param \WP_Post $post Doc.
	 * @return string
	 */
	public static function pretty_link( $post ) {
		$product = self::get_product( $post );
		$version = self::get_version( $post );
		$path    = 'docs/' . ( $product ? $product->slug : 'general' ) . '/';
		if ( $version ) {
			$path .= $version . '/';
		}
		return home_url( user_trailingslashit( $path . get_page_uri( $post ) ) );
	}

	/**
	 * Fills in the product (and version) in doc permalinks.
	 *
	 * @param string   $link      Link.
	 * @param \WP_Post $post      Post.
	 * @param bool     $leavename Keep the %doc% tag.
	 * @param bool     $sample    Sample permalink.
	 * @return string
	 */
	public function post_type_link( $link, $post, $leavename, $sample ) {
		if ( Post_Types::DOC !== $post->post_type ) {
			return $link;
		}
		$product = self::get_product( $post );
		$slug    = $product ? $product->slug : 'general';
		$link    = str_replace( '%product%', $slug, $link );

		$version = self::get_version( $post );
		if ( $version ) {
			$link = str_replace( '/docs/' . $slug . '/', '/docs/' . $slug . '/' . $version . '/', $link );
		}
		return $link;
	}

	/**
	 * Previews use the pretty URL so that relative links inside docs work in previews too.
	 *
	 * @param string   $link Preview link.
	 * @param \WP_Post $post Post.
	 * @return string
	 */
	public function preview_post_link( $link, $post ) {
		if ( Post_Types::DOC !== $post->post_type ) {
			return $link;
		}
		$query = array();
		wp_parse_str( (string) wp_parse_url( $link, PHP_URL_QUERY ), $query );
		unset( $query['p'], $query['page_id'], $query['post_type'] );
		return add_query_arg( $query, self::pretty_link( $post ) );
	}

	/**
	 * Allows the same slug in different products/versions.
	 *
	 * @param string $slug          Unique slug.
	 * @param int    $post_id       Post ID.
	 * @param string $post_status   Status.
	 * @param string $post_type     Post type.
	 * @param int    $post_parent   Parent ID.
	 * @param string $original_slug Requested slug.
	 * @return string
	 */
	public function unique_slug( $slug, $post_id, $post_status, $post_type, $post_parent, $original_slug ) {
		if ( Post_Types::DOC !== $post_type || $slug === $original_slug || ! $post_id ) {
			return $slug;
		}
		$post = get_post( $post_id );
		if ( ! $post ) {
			return $slug;
		}
		$product = self::get_product( $post );
		$version = self::get_version( $post );

		$siblings = get_posts(
			array(
				'post_type'      => Post_Types::DOC,
				'post_status'    => 'any',
				'name'           => $original_slug,
				'post_parent'    => (int) $post_parent,
				'post__not_in'   => array( (int) $post_id ),
				'posts_per_page' => -1,
			)
		);
		foreach ( $siblings as $sibling ) {
			$sibling_product = self::get_product( $sibling );
			if ( ( $sibling_product ? $sibling_product->term_id : 0 ) === ( $product ? $product->term_id : 0 ) && self::get_version( $sibling ) === $version ) {
				return $slug;
			}
		}
		return $original_slug;
	}
}
