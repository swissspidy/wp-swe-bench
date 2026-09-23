<?php
/**
 * Acme Magazine theme.json data: version, presets, defaults, variations, block styles.
 */

class ThemeJsonTest extends WPSB\TestCase {

	private function theme_dir(): string {
		return get_stylesheet_directory();
	}

	private function vars(): string {
		WP_Theme_JSON_Resolver::clean_cached_data();
		return wp_get_global_stylesheet( array( 'variables' ) );
	}

	private function var_value( string $css, string $name ): ?string {
		// The last definition wins in the cascade.
		if ( ! preg_match_all( '/--wp--preset--' . preg_quote( $name, '/' ) . ':\s*([^;}]+)[;}]/', $css, $m ) ) {
			return null;
		}
		return trim( end( $m[1] ) );
	}

	public function test_theme_is_active(): void {
		$this->assertSame( 'acme-magazine', get_stylesheet() );
		$this->assertTrue( wp_is_block_theme() );
		$this->assertSame( '2.0.0', wp_get_theme()->get( 'Version' ) );
	}

	public function test_all_theme_json_files_are_version_3(): void {
		$files = array_merge( array( $this->theme_dir() . '/theme.json' ), glob( $this->theme_dir() . '/styles/*.json' ) ?: array(), glob( $this->theme_dir() . '/styles/*/*.json' ) ?: array() );
		$this->assertGreaterThanOrEqual( 4, count( $files ) );
		foreach ( $files as $file ) {
			$data = json_decode( (string) file_get_contents( $file ), true );
			$this->assertIsArray( $data, "$file must be valid JSON" );
			$this->assertSame( 3, $data['version'] ?? null, basename( $file ) . ' must be theme.json version 3' );
		}
	}

	public function test_color_and_spacing_presets_keep_their_values(): void {
		$css = $this->vars();
		$colors = array(
			'paper'     => '#faf7f2',
			'ink'       => '#1c1917',
			'accent'    => '#c2410c',
			'muted'     => '#78716c',
			'highlight' => '#fde68a',
			'white'     => '#ffffff',
		);
		foreach ( $colors as $slug => $value ) {
			$this->assertSame( $value, strtolower( (string) $this->var_value( $css, "color--$slug" ) ), "color $slug" );
		}
		$spacing = array( '10' => '0.5rem', '20' => '1rem', '30' => '1.5rem', '40' => '2rem', '50' => '3rem', '60' => '4.5rem' );
		foreach ( $spacing as $slug => $value ) {
			$this->assertSame( $value, $this->var_value( $css, "spacing--$slug" ), "spacing $slug" );
		}
	}

	public function test_font_size_presets_and_fluid_scale(): void {
		$css = $this->vars();
		$this->assertSame( '0.875rem', $this->var_value( $css, 'font-size--small' ) );
		$this->assertSame( '1.125rem', $this->var_value( $css, 'font-size--medium' ) );
		$fluid = array(
			'large'   => array( '1.375rem', '1.75rem' ),
			'x-large' => array( '1.75rem', '2.5rem' ),
			'huge'    => array( '2.25rem', '3.5rem' ),
		);
		foreach ( $fluid as $slug => list( $min, $max ) ) {
			$value = (string) $this->var_value( $css, "font-size--$slug" );
			$this->assertMatchesRegularExpression( '/^clamp\(\s*' . preg_quote( $min, '/' ) . '\s*,.+,\s*' . preg_quote( $max, '/' ) . '\s*\)$/', $value, "font size $slug must scale from $min to $max" );
		}
		// Scaling happens between 360px and 1280px (22.5rem .. 80rem).
		$large = (string) $this->var_value( $css, 'font-size--large' );
		$this->assertStringContainsString( '1vw', $large );
		$this->assertMatchesRegularExpression( '/(22\.5rem|360px|0\.225rem|3\.6px)/', $large );
	}

	public function test_only_theme_sizes_are_offered_in_the_editor(): void {
		WP_Theme_JSON_Resolver::clean_cached_data();
		$settings = wp_get_global_settings();
		$this->assertFalse( $settings['typography']['defaultFontSizes'] ?? true, 'Default font sizes must not be offered' );
		$this->assertFalse( $settings['spacing']['defaultSpacingSizes'] ?? true, 'Default spacing sizes must not be offered' );
		$this->assertFalse( $settings['color']['defaultPalette'] ?? true );
		$this->assertFalse( $settings['color']['defaultGradients'] ?? true );

		$theme_sizes = wp_list_pluck( $settings['typography']['fontSizes']['theme'] ?? array(), 'slug' );
		$this->assertSame( array( 'small', 'medium', 'large', 'x-large', 'huge' ), $theme_sizes );
		$theme_spacing = wp_list_pluck( $settings['spacing']['spacingSizes']['theme'] ?? array(), 'slug' );
		$this->assertSame( array( '10', '20', '30', '40', '50', '60' ), $theme_spacing );
		$this->assertTrue( empty( $settings['spacing']['spacingScale']['theme'] ) || 0 === (int) ( $settings['spacing']['spacingScale']['theme']['steps'] ?? 0 ), 'No generated spacing scale on top of the theme sizes' );

		// Every color used by existing content is still a theme color.
		$palette = wp_list_pluck( $settings['color']['palette']['theme'] ?? array(), 'color', 'slug' );
		foreach ( array( 'paper', 'ink', 'accent', 'muted', 'highlight', 'white' ) as $slug ) {
			$this->assertArrayHasKey( $slug, $palette );
		}
	}

	public function test_style_variations_are_exactly_the_three_designs(): void {
		WP_Theme_JSON_Resolver::clean_cached_data();
		$variations = WP_Theme_JSON_Resolver::get_style_variations();
		$titles     = array_map( static fn( $v ) => $v['title'] ?? '', $variations );
		sort( $titles );
		$this->assertSame( array( 'Coral', 'Dusk', 'Newsprint' ), $titles );

		$expected = array(
			'Dusk'      => array( 'paper' => '#1f1b24', 'ink' => '#f1ecf6', 'accent' => '#f59e0b' ),
			'Newsprint' => array( 'paper' => '#f4f1ea', 'ink' => '#111111', 'accent' => '#111111' ),
			'Coral'     => array( 'paper' => '#fff5f2', 'ink' => '#3b0d0c', 'accent' => '#e8505b' ),
		);
		foreach ( $variations as $variation ) {
			$palette = $variation['settings']['color']['palette'] ?? array();
			$palette = wp_list_pluck( $palette['theme'] ?? $palette, 'color', 'slug' );
			foreach ( $expected[ $variation['title'] ] as $slug => $color ) {
				$this->assertSame( $color, strtolower( $palette[ $slug ] ?? '' ), $variation['title'] . " $slug" );
			}
		}

		$this->login_as( 1 );
		$res = $this->rest( 'GET', '/wp/v2/global-styles/themes/acme-magazine/variations' );
		$this->assertSame( 200, $res->get_status() );
		$rest_titles = array_map( static fn( $v ) => $v['title'] ?? '', $res->get_data() );
		sort( $rest_titles );
		$this->assertSame( array( 'Coral', 'Dusk', 'Newsprint' ), $rest_titles );
	}

	public function test_group_and_paragraph_styles_are_registered(): void {
		$styles = WP_Block_Styles_Registry::get_instance();
		$this->assertTrue( $styles->is_registered( 'core/group', 'card' ), 'Card group style' );
		$this->assertTrue( $styles->is_registered( 'core/group', 'inverted' ), 'Inverted group style' );
		$this->assertTrue( $styles->is_registered( 'core/paragraph', 'kicker' ), 'Kicker paragraph style' );
		$this->assertSame( 'Card', $styles->get_registered( 'core/group', 'card' )['label'] );
		$this->assertSame( 'Inverted', $styles->get_registered( 'core/group', 'inverted' )['label'] );
	}

	public function test_section_styles_are_part_of_the_global_styles_data(): void {
		// The Styles UI edits global styles data: Card / Inverted must live there, with nested element styles.
		WP_Theme_JSON_Resolver::clean_cached_data();
		$data       = WP_Theme_JSON_Resolver::get_merged_data()->get_raw_data();
		$variations = $data['styles']['blocks']['core/group']['variations'] ?? array();
		$this->assertArrayHasKey( 'card', $variations, 'Card must be a Group block style variation in the global styles data' );
		$this->assertArrayHasKey( 'inverted', $variations, 'Inverted must be a Group block style variation in the global styles data' );
		$card = $variations['card'];
		$this->assertSame( '12px', $card['border']['radius'] ?? null );
		$this->assertNotEmpty( $card['color']['background'] ?? null );
		$this->assertNotEmpty( $card['shadow'] ?? null );
		$inverted = $variations['inverted'];
		$this->assertNotEmpty( $inverted['color']['background'] ?? null );
		$this->assertNotEmpty( $inverted['elements']['link']['color']['text'] ?? null, 'Inverted link color must be a nested element style' );
		$heading = $inverted['elements']['heading']['color']['text'] ?? ( $inverted['elements']['h2']['color']['text'] ?? null );
		$this->assertNotEmpty( $heading, 'Inverted heading color must be a nested element style' );

		$kicker = $data['styles']['blocks']['core/paragraph']['variations']['kicker'] ?? array();
		$this->assertSame( 'uppercase', $kicker['typography']['textTransform'] ?? null, 'Kicker must be defined in the global styles data' );

		$link = $data['styles']['elements']['link'][':hover']['color']['text'] ?? null;
		$this->assertNotEmpty( $link, 'Link hover color must be defined in the global styles data' );
		$this->assertNotEmpty( $data['styles']['elements']['button']['border']['radius'] ?? ( $data['styles']['blocks']['core/button']['border']['radius'] ?? null ), 'Button radius must be defined in the global styles data' );
	}

	public function test_seeded_content_still_uses_the_presets(): void {
		$post = get_page_by_path( 'style-guide', OBJECT, 'post' );
		$this->assertNotNull( $post );
		$GLOBALS['post'] = $post;
		setup_postdata( $post );
		$html = apply_filters( 'the_content', $post->post_content );
		wp_reset_postdata();
		foreach ( array( 'has-huge-font-size', 'has-large-font-size', 'has-accent-color', 'has-highlight-background-color', 'is-style-card', 'is-style-inverted', 'is-style-kicker', 'var(--wp--preset--spacing--40)' ) as $needle ) {
			$this->assertStringContainsString( $needle, $html );
		}
		$css = wp_get_global_stylesheet( array( 'presets' ) );
		$this->assertMatchesRegularExpression( '/\.has-huge-font-size\s*\{\s*font-size:\s*var\(--wp--preset--font-size--huge\)/', $css );
		$this->assertMatchesRegularExpression( '/\.has-accent-color\s*\{\s*color:\s*var\(--wp--preset--color--accent\)/', $css );
	}
}
