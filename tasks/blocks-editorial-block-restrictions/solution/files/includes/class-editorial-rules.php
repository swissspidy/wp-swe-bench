<?php
/**
 * Editorial block rules: which blocks may be used, per post type and per role,
 * and which design tools are switched off for which roles.
 *
 * Stored in the `acme_newsroom_block_rules` option:
 *
 *     array(
 *         'post_types' => array(
 *             'post' => array(
 *                 'allowed' => null,                       // null = every block
 *                 'roles'   => array(
 *                     'contributor' => array( 'core/paragraph', 'core/list', 'acme/*' ),
 *                 ),
 *             ),
 *             'press_release' => array(
 *                 'allowed' => array( 'core/paragraph', 'core/heading', 'acme/*' ),
 *                 'roles'   => array(),
 *             ),
 *         ),
 *         'disabled_design_tools' => array(
 *             'contributor' => array( 'custom_colors', 'custom_font_sizes' ),
 *         ),
 *     )
 *
 * @package Acme\Newsroom
 */

namespace Acme\Newsroom;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the editorial rules.
 */
class Editorial_Rules {

	const OPTION = 'acme_newsroom_block_rules';

	/**
	 * Design tools that can be switched off per role.
	 */
	const DESIGN_TOOLS = array( 'custom_colors', 'custom_font_sizes' );

	/**
	 * The rules (sanitized).
	 *
	 * @return array
	 */
	public static function get() {
		return self::sanitize( get_option( self::OPTION, array() ) );
	}

	/**
	 * Sanitize a block list: block names ("core/paragraph") or namespace patterns ("acme/*").
	 *
	 * @param mixed $list Raw list (array or newline separated string).
	 * @return string[]
	 */
	public static function sanitize_block_list( $list ) {
		if ( is_string( $list ) ) {
			$list = preg_split( '/[\r\n,]+/', $list );
		}
		$out = array();
		foreach ( (array) $list as $entry ) {
			$entry = strtolower( trim( (string) $entry ) );
			if ( preg_match( '#^[a-z0-9-]+/([a-z0-9-]+|\*)$#', $entry ) ) {
				$out[] = $entry;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Sanitize the whole option.
	 *
	 * @param mixed $input Raw option value.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$rules = array(
			'post_types'            => array(),
			'disabled_design_tools' => array(),
		);

		foreach ( ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) ? $input['post_types'] : array() as $post_type => $type_rules ) {
			$post_type  = sanitize_key( $post_type );
			$type_rules = is_array( $type_rules ) ? $type_rules : array();
			if ( '' === $post_type ) {
				continue;
			}
			$allowed = isset( $type_rules['allowed'] ) && null !== $type_rules['allowed'] && '' !== $type_rules['allowed']
				? self::sanitize_block_list( $type_rules['allowed'] )
				: null;
			$roles   = array();
			foreach ( ( isset( $type_rules['roles'] ) && is_array( $type_rules['roles'] ) ) ? $type_rules['roles'] : array() as $role => $list ) {
				$role = sanitize_key( $role );
				if ( '' === $role || null === $list || '' === $list ) {
					continue;
				}
				$roles[ $role ] = self::sanitize_block_list( $list );
			}
			$rules['post_types'][ $post_type ] = array(
				'allowed' => $allowed,
				'roles'   => $roles,
			);
		}

		foreach ( ( isset( $input['disabled_design_tools'] ) && is_array( $input['disabled_design_tools'] ) ) ? $input['disabled_design_tools'] : array() as $role => $tools ) {
			$role  = sanitize_key( $role );
			$tools = array_values( array_intersect( self::DESIGN_TOOLS, (array) $tools ) );
			if ( '' !== $role && $tools ) {
				$rules['disabled_design_tools'][ $role ] = $tools;
			}
		}

		return $rules;
	}

	/**
	 * Whether a block name is in a list (supports "namespace/*").
	 *
	 * @param string   $block_name Block name.
	 * @param string[] $list       Block list.
	 * @return bool
	 */
	public static function in_list( $block_name, array $list ) {
		foreach ( $list as $pattern ) {
			if ( acme_newsroom_block_matches( $block_name, $pattern ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether the user is exempt from role rules (site administrators).
	 *
	 * @param \WP_User $user User.
	 * @return bool
	 */
	public static function is_exempt( $user ) {
		return $user instanceof \WP_User && $user->exists() && user_can( $user, 'manage_options' );
	}

	/**
	 * The effective allow-list (block patterns) for a user on a post type.
	 *
	 * - The post type list applies to everyone (null = no restriction).
	 * - Role lists restrict further; a user with several roles gets the
	 *   blocks of all their roles that have a list. Administrators are exempt.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_User $user      User.
	 * @return array{0: string[]|null, 1: string[]|null} [ post type list, combined role list ] (null = unrestricted).
	 */
	public static function lists_for( $post_type, $user ) {
		$rules = self::get();
		if ( empty( $rules['post_types'][ $post_type ] ) ) {
			return array( null, null );
		}
		$type_rules = $rules['post_types'][ $post_type ];
		$role_list  = null;
		if ( ! self::is_exempt( $user ) && $user instanceof \WP_User ) {
			foreach ( (array) $user->roles as $role ) {
				if ( isset( $type_rules['roles'][ $role ] ) ) {
					$role_list = array_merge( (array) $role_list, $type_rules['roles'][ $role ] );
				}
			}
		}
		return array( $type_rules['allowed'], null === $role_list ? null : array_values( array_unique( $role_list ) ) );
	}

	/**
	 * Allowed block names for a user on a post type, expanded against the
	 * registered blocks. Child blocks (with a parent/ancestor) are allowed
	 * whenever one of their possible parents is allowed.
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_User $user      User.
	 * @return string[]|null Block names, or null when nothing is restricted.
	 */
	public static function allowed_blocks( $post_type, $user ) {
		list( $type_list, $role_list ) = self::lists_for( $post_type, $user );
		if ( null === $type_list && null === $role_list ) {
			return null;
		}

		$registered = \WP_Block_Type_Registry::get_instance()->get_all_registered();
		$allowed    = array();
		foreach ( array_keys( $registered ) as $name ) {
			if ( ( null === $type_list || self::in_list( $name, $type_list ) ) && ( null === $role_list || self::in_list( $name, $role_list ) ) ) {
				$allowed[ $name ] = true;
			}
		}

		// Children of allowed blocks (list items, columns, buttons…), repeated for nested levels.
		do {
			$added = false;
			foreach ( $registered as $name => $block_type ) {
				if ( isset( $allowed[ $name ] ) ) {
					continue;
				}
				$parents = array_merge( (array) $block_type->parent, (array) $block_type->ancestor );
				foreach ( $parents as $parent ) {
					if ( isset( $allowed[ $parent ] ) ) {
						$allowed[ $name ] = true;
						$added            = true;
						break;
					}
				}
			}
		} while ( $added );

		return array_keys( $allowed );
	}

	/**
	 * Design tools switched off for a user (all roles combined; administrators are exempt).
	 *
	 * @param \WP_User $user User.
	 * @return string[]
	 */
	public static function disabled_design_tools( $user ) {
		if ( ! $user instanceof \WP_User || self::is_exempt( $user ) ) {
			return array();
		}
		$rules = self::get();
		$tools = array();
		foreach ( (array) $user->roles as $role ) {
			if ( isset( $rules['disabled_design_tools'][ $role ] ) ) {
				$tools = array_merge( $tools, $rules['disabled_design_tools'][ $role ] );
			}
		}
		return array_values( array_unique( $tools ) );
	}
}
