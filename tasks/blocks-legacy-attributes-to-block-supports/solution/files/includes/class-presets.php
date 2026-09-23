<?php
/**
 * The active theme's design tokens (palette, spacing sizes, font sizes).
 *
 * @package Acme\ContentBlocks
 */

namespace Acme\ContentBlocks;

defined( 'ABSPATH' ) || exit;

/**
 * Looks up theme presets by value and by slug.
 *
 * Only the theme's own presets count (not WordPress' default palette or
 * sizes, and not user-defined custom presets).
 */
class Presets {

	/**
	 * Theme origin of a preset list from the global settings.
	 *
	 * @param string[] $path Settings path, e.g. array( 'color', 'palette' ).
	 * @return array[]
	 */
	private static function theme_presets( array $path ) {
		$presets = wp_get_global_settings( $path );
		if ( ! is_array( $presets ) ) {
			return array();
		}
		// Presets are keyed by origin (default, theme, custom).
		if ( isset( $presets['theme'] ) && is_array( $presets['theme'] ) ) {
			return $presets['theme'];
		}
		return array_key_exists( 'default', $presets ) || array_key_exists( 'custom', $presets ) ? array() : $presets;
	}

	/**
	 * Theme palette: slug => normalized hex.
	 *
	 * @return array<string, string>
	 */
	public static function colors() {
		$colors = array();
		foreach ( self::theme_presets( array( 'color', 'palette' ) ) as $preset ) {
			$hex = Colors::normalize_hex( $preset['color'] ?? '' );
			if ( isset( $preset['slug'] ) && '' !== $hex ) {
				$colors[ (string) $preset['slug'] ] = $hex;
			}
		}
		return $colors;
	}

	/**
	 * Theme spacing sizes: slug => size string.
	 *
	 * @return array<string, string>
	 */
	public static function spacing_sizes() {
		return self::sizes( array( 'spacing', 'spacingSizes' ) );
	}

	/**
	 * Theme font sizes: slug => size string.
	 *
	 * @return array<string, string>
	 */
	public static function font_sizes() {
		return self::sizes( array( 'typography', 'fontSizes' ) );
	}

	/**
	 * Slug => size for a size preset list.
	 *
	 * @param string[] $path Settings path.
	 * @return array<string, string>
	 */
	private static function sizes( array $path ) {
		$sizes = array();
		foreach ( self::theme_presets( $path ) as $preset ) {
			if ( isset( $preset['slug'], $preset['size'] ) && is_string( $preset['size'] ) ) {
				$sizes[ (string) $preset['slug'] ] = trim( $preset['size'] );
			}
		}
		return $sizes;
	}

	/**
	 * Palette slug for a hex colour, or null.
	 *
	 * @param string $hex Colour.
	 * @return string|null
	 */
	public static function color_slug( $hex ) {
		$hex = Colors::normalize_hex( $hex );
		if ( '' === $hex ) {
			return null;
		}
		$slug = array_search( $hex, self::colors(), true );
		return false === $slug ? null : (string) $slug;
	}

	/**
	 * Preset slug whose size is exactly "{$px}px", or null.
	 *
	 * @param array<string, string> $sizes Slug => size.
	 * @param int|float             $px    Pixels.
	 * @return string|null
	 */
	public static function size_slug( array $sizes, $px ) {
		$slug = array_search( self::px( $px ), $sizes, true );
		return false === $slug ? null : (string) $slug;
	}

	/**
	 * "{$n}px" for a number.
	 *
	 * @param int|float $n Number.
	 * @return string
	 */
	public static function px( $n ) {
		return ( floor( (float) $n ) === (float) $n ? (string) (int) $n : (string) (float) $n ) . 'px';
	}

	/**
	 * Integer pixels of a CSS size like "16px", or null.
	 *
	 * @param mixed $size Size.
	 * @return int|null
	 */
	public static function to_px( $size ) {
		if ( is_int( $size ) || is_float( $size ) ) {
			return (int) round( $size );
		}
		if ( is_string( $size ) && preg_match( '/^\s*(\d+(?:\.\d+)?)px\s*$/', $size, $m ) ) {
			return (int) round( (float) $m[1] );
		}
		return null;
	}

	/**
	 * Settings for the editor scripts (used by the block deprecations).
	 *
	 * @return array
	 */
	public static function for_editor() {
		$list = static function ( array $map, $key ) {
			$out = array();
			foreach ( $map as $slug => $value ) {
				$out[] = array(
					'slug' => (string) $slug,
					$key   => $value,
				);
			}
			return $out;
		};
		return array(
			'colors'       => $list( self::colors(), 'color' ),
			'spacingSizes' => $list( self::spacing_sizes(), 'size' ),
			'fontSizes'    => $list( self::font_sizes(), 'size' ),
		);
	}
}
