<?php
/**
 * Converts the bespoke 1.x attributes to the standard block supports
 * representation. Mirrors src/shared/legacy.js, which does the same in the
 * editor, so un-migrated content renders exactly like migrated content.
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Legacy attribute migration.
 */
class Migrator {

	/**
	 * Does a notice box still carry 1.x attributes?
	 *
	 * `textColor` and `fontSize` exist in both formats: in 1.x they are a hex
	 * colour and a number, in 2.x a palette slug and a font size slug.
	 *
	 * @param array $attrs Attributes.
	 * @return bool
	 */
	public static function notice_is_legacy( array $attrs ) {
		return array_key_exists( 'bgColor', $attrs )
			|| array_key_exists( 'padding', $attrs )
			|| array_key_exists( 'bordered', $attrs )
			|| ( isset( $attrs['textColor'] ) && self::looks_like_color_value( $attrs['textColor'] ) )
			|| ( isset( $attrs['fontSize'] ) && is_numeric( $attrs['fontSize'] ) );
	}

	/**
	 * Does a statistic still carry 1.x attributes?
	 *
	 * @param array $attrs Attributes.
	 * @return bool
	 */
	public static function stat_is_legacy( array $attrs ) {
		return array_key_exists( 'color', $attrs )
			|| array_key_exists( 'boxed', $attrs )
			|| ( isset( $attrs['fontSize'] ) && is_numeric( $attrs['fontSize'] ) );
	}

	/**
	 * A 1.x colour value (hex or anything else that isn't a slug)?
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function looks_like_color_value( $value ) {
		return is_string( $value ) && ( 0 === strpos( trim( $value ), '#' ) || '' !== Colors::normalize_hex( $value ) );
	}

	/**
	 * Migrate notice box attributes.
	 *
	 * @param array $attrs 1.x attributes.
	 * @return array 2.x attributes.
	 */
	public static function migrate_notice( array $attrs ) {
		if ( ! self::notice_is_legacy( $attrs ) ) {
			return $attrs;
		}
		$legacy_text = $attrs['textColor'] ?? null;
		$legacy_size = $attrs['fontSize'] ?? null;
		$new         = $attrs;
		unset( $new['bgColor'], $new['textColor'], $new['padding'], $new['fontSize'], $new['bordered'] );

		$new = self::apply_color( $new, $attrs['bgColor'] ?? null, 'backgroundColor', 'background' );
		$new = self::apply_color( $new, is_string( $legacy_text ) && self::looks_like_color_value( $legacy_text ) ? $legacy_text : null, 'textColor', 'text' );

		if ( isset( $attrs['padding'] ) && is_numeric( $attrs['padding'] ) && (float) $attrs['padding'] > 0 ) {
			$slug  = Presets::size_slug( Presets::spacing_sizes(), $attrs['padding'] );
			$value = null !== $slug ? 'var:preset|spacing|' . $slug : Presets::px( $attrs['padding'] );
			$new   = self::set_style( $new, array( 'spacing', 'padding' ), array_fill_keys( array( 'top', 'right', 'bottom', 'left' ), $value ) );
		}

		$new = self::apply_font_size( $new, is_numeric( $legacy_size ) ? $legacy_size : null );

		if ( ! empty( $attrs['bordered'] ) ) {
			$new['className'] = self::add_class( $new['className'] ?? '', 'is-style-outlined' );
		}
		return $new;
	}

	/**
	 * Migrate statistic attributes.
	 *
	 * @param array $attrs 1.x attributes.
	 * @return array 2.x attributes.
	 */
	public static function migrate_stat( array $attrs ) {
		if ( ! self::stat_is_legacy( $attrs ) ) {
			return $attrs;
		}
		$legacy_size = $attrs['fontSize'] ?? null;
		$new         = $attrs;
		unset( $new['color'], $new['fontSize'], $new['boxed'] );

		$new = self::apply_color( $new, $attrs['color'] ?? null, 'textColor', 'text' );
		$new = self::apply_font_size( $new, is_numeric( $legacy_size ) ? $legacy_size : null );
		if ( ! empty( $attrs['boxed'] ) ) {
			$new['className'] = self::add_class( $new['className'] ?? '', 'is-style-card' );
		}
		return $new;
	}

	/**
	 * Preset or custom colour.
	 *
	 * @param array       $attrs     Attributes being built.
	 * @param string|null $value     Legacy colour.
	 * @param string      $attribute Preset attribute (backgroundColor/textColor).
	 * @param string      $style_key style.color key (background/text).
	 * @return array
	 */
	private static function apply_color( array $attrs, $value, $attribute, $style_key ) {
		$hex = Colors::normalize_hex( $value );
		if ( '' === $hex ) {
			return $attrs;
		}
		$slug = Presets::color_slug( $hex );
		if ( null !== $slug ) {
			$attrs[ $attribute ] = $slug;
			return $attrs;
		}
		return self::set_style( $attrs, array( 'color', $style_key ), $hex );
	}

	/**
	 * Preset or custom font size.
	 *
	 * @param array          $attrs Attributes being built.
	 * @param int|float|null $px    Legacy pixels.
	 * @return array
	 */
	private static function apply_font_size( array $attrs, $px ) {
		if ( null === $px || (float) $px <= 0 ) {
			return $attrs;
		}
		$slug = Presets::size_slug( Presets::font_sizes(), $px );
		if ( null !== $slug ) {
			$attrs['fontSize'] = $slug;
			return $attrs;
		}
		return self::set_style( $attrs, array( 'typography', 'fontSize' ), Presets::px( $px ) );
	}

	/**
	 * Set a value in the `style` attribute.
	 *
	 * @param array    $attrs Attributes.
	 * @param string[] $path  Path inside `style`.
	 * @param mixed    $value Value.
	 * @return array
	 */
	private static function set_style( array $attrs, array $path, $value ) {
		$style = isset( $attrs['style'] ) && is_array( $attrs['style'] ) ? $attrs['style'] : array();
		_wp_array_set( $style, $path, $value );
		$attrs['style'] = $style;
		return $attrs;
	}

	/**
	 * Append a class to a class list once.
	 *
	 * @param string $classes Existing classes.
	 * @param string $class   Class to add.
	 * @return string
	 */
	private static function add_class( $classes, $class ) {
		$list = preg_split( '/\s+/', trim( (string) $classes ), -1, PREG_SPLIT_NO_EMPTY );
		if ( ! in_array( $class, $list, true ) ) {
			$list[] = $class;
		}
		return implode( ' ', $list );
	}
}
