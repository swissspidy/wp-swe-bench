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
	 * Blocks to hide from the inserter for a user on a post type (used by the
	 * editor workarounds).
	 *
	 * @param string   $post_type Post type.
	 * @param \WP_User $user      User.
	 * @return string[] Block names.
	 */
	public static function hidden_blocks( $post_type, $user ) {
		$rules = self::get();
		if ( empty( $rules['post_types'][ $post_type ] ) ) {
			return array();
		}
		$type_rules = $rules['post_types'][ $post_type ];
		// Main role only – good enough for our editors.
		$role = isset( $user->roles[0] ) ? $user->roles[0] : '';

		$hidden = array();
		foreach ( \WP_Block_Type_Registry::get_instance()->get_all_registered() as $name => $block_type ) {
			if ( null !== $type_rules['allowed'] && ! self::in_list( $name, $type_rules['allowed'] ) ) {
				$hidden[] = $name;
			} elseif ( isset( $type_rules['roles'][ $role ] ) && ! self::in_list( $name, $type_rules['roles'][ $role ] ) ) {
				$hidden[] = $name;
			}
		}
		return $hidden;
	}

	/**
	 * Design tools switched off for a user.
	 *
	 * @param \WP_User $user User.
	 * @return string[]
	 */
	public static function disabled_design_tools( $user ) {
		$rules = self::get();
		$role  = isset( $user->roles[0] ) ? $user->roles[0] : '';
		return isset( $rules['disabled_design_tools'][ $role ] ) ? $rules['disabled_design_tools'][ $role ] : array();
	}
}
