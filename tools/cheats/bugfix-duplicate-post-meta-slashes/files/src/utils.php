<?php

namespace Yoast\WP\Duplicate_Post;

use WP_Post;

/**
 * Utility methods for Duplicate Post.
 *
 * @since 4.0
 */
class Utils {

	/**
	 * Taxonomies that can be copied, keyed by post type.
	 *
	 * @var array<string, array<string>>
	 */
	private static $post_type_taxonomies = [];

	/**
	 * Adds slashes only to strings.
	 *
	 * @param mixed $value Value to slash only if string.
	 *
	 * @return string|mixed
	 */
	public static function addslashes_to_strings_only( $value ) {
		return \is_string( $value ) ? \addslashes( $value ) : $value;
	}

	/**
	 * Slashes a value before handing it to a WordPress function that expects slashed data.
	 *
	 * Core's wp_slash() only walks arrays, while wp_unslash() (used by the functions that store
	 * post data and meta) walks arrays AND objects. Strings nested in objects must therefore be
	 * slashed as well, or their backslashes are stripped when the value is stored.
	 * Non-string scalars are left untouched so that their type is preserved.
	 *
	 * @param mixed $value What to add slashes to.
	 *
	 * @return mixed
	 */
	public static function recursively_slash_strings( $value ) {
		if ( \is_object( $value ) ) {
			// map_deep() changes objects in place: never alter the caller's object.
			$value = \unserialize( \serialize( $value ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Deep clone.
		}

		return \map_deep( $value, [ self::class, 'addslashes_to_strings_only' ] );
	}

	/**
	 * Prepares a raw meta value, as stored in the database, to be passed to add_post_meta() and similar.
	 *
	 * Every value is unserialized (if needed) and slashed, because the meta API unslashes all the
	 * values it receives, including plain strings (JSON, regexes, Windows paths...).
	 *
	 * @param string $meta_value The raw meta value.
	 *
	 * @return mixed The value ready to be stored.
	 */
	public static function prepare_meta_value_for_copy( $meta_value ) {
		return self::recursively_slash_strings( \maybe_unserialize( $meta_value ) );
	}

	/**
	 * Maps post types to the taxonomies that can be copied for them.
	 *
	 * Resolving the taxonomies once per request avoids looping over all the registered
	 * taxonomies for every single post when copying many posts (bulk actions, children).
	 *
	 * @param array<string> $post_types The post types to map.
	 *
	 * @return void
	 */
	public static function map_post_type_taxonomies( $post_types ) {
		foreach ( (array) $post_types as $post_type ) {
			$taxonomies = \get_object_taxonomies( $post_type );
			// Several plugins just add support to post-formats but don't register post_format taxonomy.
			if ( \post_type_supports( $post_type, 'post-formats' ) && ! \in_array( 'post_format', $taxonomies, true ) ) {
				$taxonomies[] = 'post_format';
			}
			self::$post_type_taxonomies[ $post_type ] = $taxonomies;
		}
	}

	/**
	 * Gets the taxonomies that can be copied for a post type.
	 *
	 * @param string $post_type The post type.
	 *
	 * @return array<string> The taxonomy names.
	 */
	public static function get_post_type_taxonomies( $post_type ) {
		if ( ! isset( self::$post_type_taxonomies[ $post_type ] ) ) {
			self::map_post_type_taxonomies( [ $post_type ] );
		}

		return self::$post_type_taxonomies[ $post_type ];
	}

	/**
	 * Gets the original post.
	 *
	 * @param int|WP_Post|null $post   Optional. Post ID or Post object.
	 * @param string           $output Optional, default is Object. Either OBJECT, ARRAY_A, or ARRAY_N.
	 *
	 * @return WP_Post|null Post data if successful, null otherwise.
	 */
	public static function get_original( $post = null, $output = \OBJECT ) {
		$post = \get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$original_id = self::get_original_post_id( $post->ID );

		if ( empty( $original_id ) ) {
			return null;
		}

		return \get_post( $original_id, $output );
	}

	/**
	 * Determines if the post has ancestors marked for copy.
	 *
	 * If we are copying children, and the post has already an ancestor marked for copy, we have to filter it out.
	 *
	 * @param WP_Post $post     The post object.
	 * @param array   $post_ids The array of marked post IDs.
	 *
	 * @return bool Whether the post has ancestors marked for copy.
	 */
	public static function has_ancestors_marked( $post, $post_ids ) {
		$ancestors_in_array = 0;
		$parent             = \wp_get_post_parent_id( $post->ID );
		while ( $parent ) {
			if ( \in_array( $parent, $post_ids, true ) ) {
				++$ancestors_in_array;
			}
			$parent = \wp_get_post_parent_id( $parent );
		}
		return ( $ancestors_in_array !== 0 );
	}

	/**
	 * Returns a link to edit, preview or view a post, in accordance to user capabilities.
	 *
	 * @param WP_Post $post Post ID or Post object.
	 *
	 * @return string|null The link to edit, preview or view a post.
	 */
	public static function get_edit_or_view_link( $post ) {
		$post = \get_post( $post );
		if ( ! $post ) {
			return null;
		}

		$can_edit_post    = \current_user_can( 'edit_post', $post->ID );
		$title            = \_draft_or_post_title( $post );
		$post_type_object = \get_post_type_object( $post->post_type );

		if ( $can_edit_post && $post->post_status !== 'trash' ) {
			return \sprintf(
				'<a href="%s" aria-label="%s">%s</a>',
				\esc_url( \get_edit_post_link( $post->ID ) ),
				/* translators: Hidden accessibility text; %s: post title */
				\esc_attr( \sprintf( \__( 'Edit &#8220;%s&#8221;', 'duplicate-post' ), $title ) ),
				$title,
			);
		}
		elseif ( \is_post_type_viewable( $post_type_object ) ) {
			if ( \in_array( $post->post_status, [ 'pending', 'draft', 'future' ], true ) ) {
				if ( $can_edit_post ) {
					$preview_link = \get_preview_post_link( $post );
					return \sprintf(
						'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
						\esc_url( $preview_link ),
						/* translators: Hidden accessibility text; %s: post title */
						\esc_attr( \sprintf( \__( 'Preview &#8220;%s&#8221;', 'duplicate-post' ), $title ) ),
						$title,
					);
				}
			}
			elseif ( $post->post_status !== 'trash' ) {
				return \sprintf(
					'<a href="%s" rel="bookmark" aria-label="%s">%s</a>',
					\esc_url( \get_permalink( $post->ID ) ),
					/* translators: Hidden accessibility text; %s: post title */
					\esc_attr( \sprintf( \__( 'View &#8220;%s&#8221;', 'duplicate-post' ), $title ) ),
					$title,
				);
			}
		}

		return $title;
	}

	/**
	 * Gets the ID of the original post intended to be rewritten with the copy for Rewrite & Republish.
	 *
	 * @param int $post_id The copy post ID.
	 *
	 * @return int The original post id of a copy for Rewrite & Republish.
	 */
	public static function get_original_post_id( $post_id ) {
		return (int) \get_post_meta( $post_id, '_dp_original', true );
	}

	/**
	 * Gets the registered WordPress roles.
	 *
	 * @codeCoverageIgnore As this is a simple wrapper method for a built-in WordPress method, we don't have to test it.
	 *
	 * @return array The roles.
	 */
	public static function get_roles() {
		global $wp_roles;

		return $wp_roles->get_names();
	}

	/**
	 * Gets the default meta field names to be filtered out.
	 *
	 * @return array<string> The names of the meta fields to filter out by default.
	 */
	public static function get_default_filtered_meta_names() {
		return [
			'_edit_lock',
			'_edit_last',
			'_dp_original',
			'_dp_is_rewrite_republish_copy',
			'_dp_has_rewrite_republish_copy',
			'_dp_has_been_republished',
			'_dp_creation_date_gmt',
		];
	}

	/**
	 * Gets a Duplicate Post option from the database.
	 *
	 * @param string $option The option to get.
	 * @param string $key    The key to retrieve, if the option is an array.
	 *
	 * @return mixed The option.
	 */
	public static function get_option( $option, $key = '' ) {
		$option = \get_option( $option );

		if ( ! \is_array( $option ) || empty( $key ) ) {
			return $option;
		}

		if ( ! \array_key_exists( $key, $option ) ) {
			return '';
		}

		return $option[ $key ];
	}

	/**
	 * Determines if a plugin is active.
	 *
	 * We can't use is_plugin_active because this must work on the frontend too.
	 *
	 * @param string $plugin Path to the plugin file relative to the plugins directory.
	 *
	 * @return bool Whether a plugin is currently active.
	 */
	public static function is_plugin_active( $plugin ) {
		if ( \in_array( $plugin, (array) \get_option( 'active_plugins', [] ), true ) ) {
			return true;
		}

		if ( ! \is_multisite() ) {
			return false;
		}

		$plugins = \get_site_option( 'active_sitewide_plugins' );
		return isset( $plugins[ $plugin ] );
	}
}
