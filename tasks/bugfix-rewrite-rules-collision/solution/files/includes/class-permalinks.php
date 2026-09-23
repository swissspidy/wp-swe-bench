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
 * Routing of these URLs is done by Router. Slugs only have to be unique among siblings of the same
 * product and version, so
 * `/docs/acme-cloud/getting-started/installation/` and `/docs/acme-cli/getting-started/installation/`
 * can coexist.
 */
class Permalinks {

	/**
	 * Hooks.
	 */
	public function register() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
		add_filter( 'post_type_link', array( $this, 'post_type_link' ), 10, 4 );
		add_filter( 'term_link', array( $this, 'term_link' ), 10, 3 );
		add_filter( 'post_type_archive_link', array( $this, 'archive_link' ), 10, 2 );
		add_filter( 'wp_unique_post_slug', array( $this, 'unique_slug' ), 10, 6 );
	}

	/**
	 * Whether pretty permalinks are enabled.
	 *
	 * @return bool
	 */
	protected static function pretty_permalinks() {
		return (bool) get_option( 'permalink_structure' );
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
	 * Pretty URL of a doc (`/docs/{product}/[{version}/]{path}/`), or null if the doc has no product.
	 *
	 * @param \WP_Post $post      Doc.
	 * @param bool     $leavename Use the `%pagename%` placeholder for the doc's own slug (sample permalinks).
	 * @return string|null
	 */
	public static function pretty_link( $post, $leavename = false ) {
		$product = self::get_product( $post );
		if ( ! $product ) {
			return null;
		}
		$version = self::get_version( $post );
		$path    = 'docs/' . $product->slug . '/';
		if ( $version ) {
			$path .= $version . '/';
		}
		if ( $leavename ) {
			// Like core's permastructs for hierarchical post types, the placeholder stands for the whole
			// path; the editor adds the parents' path itself.
			$uri = '%pagename%';
		} else {
			$uri = get_page_uri( $post );
		}
		if ( '' === (string) $uri ) {
			return null;
		}
		return home_url( user_trailingslashit( $path . $uri ) );
	}

	/**
	 * Doc permalinks.
	 *
	 * Unpublished docs keep WordPress' query-string link (they have no final slug yet, and their
	 * previews must work), except for the sample permalink shown in the editor.
	 *
	 * @param string   $link      Link.
	 * @param \WP_Post $post      Post.
	 * @param bool     $leavename Keep the slug placeholder.
	 * @param bool     $sample    Sample permalink.
	 * @return string
	 */
	public function post_type_link( $link, $post, $leavename, $sample ) {
		if ( Post_Types::DOC !== $post->post_type || ! self::pretty_permalinks() ) {
			return $link;
		}
		$unpublished = in_array( $post->post_status, array( 'draft', 'pending', 'auto-draft', 'future' ), true );
		if ( $unpublished && ! $sample ) {
			return $link;
		}
		$pretty = self::pretty_link( $post, $leavename );
		if ( null === $pretty ) {
			return add_query_arg(
				array(
					'post_type' => Post_Types::DOC,
					'p'         => $post->ID,
				),
				home_url( '/' )
			);
		}
		return $pretty;
	}

	/**
	 * Product archive links: `/docs/{product}/`.
	 *
	 * @param string   $link     Link.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 * @return string
	 */
	public function term_link( $link, $term, $taxonomy ) {
		if ( Post_Types::PRODUCT !== $taxonomy || ! self::pretty_permalinks() ) {
			return $link;
		}
		return home_url( user_trailingslashit( 'docs/' . $term->slug ) );
	}

	/**
	 * Docs archive link: `/docs/`.
	 *
	 * @param string $link      Link.
	 * @param string $post_type Post type.
	 * @return string
	 */
	public function archive_link( $link, $post_type ) {
		if ( Post_Types::DOC !== $post_type || ! self::pretty_permalinks() ) {
			return $link;
		}
		return home_url( user_trailingslashit( 'docs' ) );
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
