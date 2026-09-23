<?php
/**
 * Helpers for the Acme UI Kit asset tests: which of the kit's assets a page loads, and how.
 */

namespace WPSB\UI;

const PLUGIN_PATH = '/wp-content/plugins/acme-ui-kit/';

/**
 * Assets of a HTML document.
 *
 * @return array{scripts: array<string, array{pos:int, src:string, attrs:string, defer:bool, async:bool}>, styles: array<string, int>, inline: array<string, int>, plugin_urls: string[]}
 */
function page_assets( string $html ): array {
	$out = array(
		'scripts'     => array(),
		'styles'      => array(),
		'inline'      => array(),
		'plugin_urls' => array(),
	);
	preg_match_all( '#<script\b([^>]*)>#i', $html, $m, PREG_OFFSET_CAPTURE );
	foreach ( $m[1] as $i => $attr ) {
		$attrs = $attr[0];
		if ( ! preg_match( '#\bid=([\'"])([^\'"]+)\1#', $attrs, $idm ) ) {
			continue;
		}
		$id = $idm[2];
		if ( preg_match( '#^(.+)-js-(before|after|extra|translations)$#', $id, $im ) ) {
			$out['inline'][ $im[1] . '-' . $im[2] ] = $m[0][ $i ][1];
			continue;
		}
		if ( ! preg_match( '#^(.+)-js$#', $id, $hm ) ) {
			continue;
		}
		preg_match( '#\bsrc=([\'"])([^\'"]+)\1#', $attrs, $sm );
		$out['scripts'][ $hm[1] ] = array(
			'pos'   => $m[0][ $i ][1],
			'src'   => $sm[2] ?? '',
			'attrs' => $attrs,
			'defer' => (bool) preg_match( '#\sdefer(\s|=|$)#i', ' ' . $attrs ),
			'async' => (bool) preg_match( '#\sasync(\s|=|$)#i', ' ' . $attrs ),
		);
	}
	preg_match_all( '#<(link|style)\b([^>]*)>#i', $html, $m, PREG_OFFSET_CAPTURE );
	foreach ( $m[2] as $i => $attr ) {
		if ( ! preg_match( '#\bid=([\'"])([^\'"]+)\1#', $attr[0], $idm ) ) {
			continue;
		}
		$id = $idm[2];
		if ( preg_match( '#^(.+)-inline-css$#', $id, $hm ) || preg_match( '#^(.+)-css$#', $id, $hm ) ) {
			$out['styles'][ $hm[1] ] = $out['styles'][ $hm[1] ] ?? $m[0][ $i ][1];
		}
	}
	preg_match_all( '#(?:src|href)=([\'"])([^\'"]*' . preg_quote( PLUGIN_PATH, '#' ) . '[^\'"]*)\1#', $html, $m );
	$out['plugin_urls'] = array_values( array_unique( $m[2] ) );
	return $out;
}

/** Script handles of the kit on the page (served from the plugin directory). */
function kit_scripts( array $assets ): array {
	return array_filter(
		$assets['scripts'],
		static fn( $s ) => false !== strpos( $s['src'], PLUGIN_PATH )
	);
}

/**
 * Base class: fetch pages from the server.
 */
abstract class AssetsTestCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	protected function fetch( string $path, ?int $user = null ): array {
		$opts = array();
		if ( $user ) {
			$opts['login'] = $this->http_login( $user );
		}
		$r = $this->http( 'GET', $path, $opts );
		$this->assertSame( 200, $r['status'], "GET $path: " . substr( $r['body'], 0, 300 ) );
		$this->assertStringNotContainsString( 'Fatal error', $r['body'], $path );
		return array( $r['body'], page_assets( $r['body'] ) );
	}

	protected function assertNoKitAssets( string $path, ?int $user = null ): void {
		list( $html, $a ) = $this->fetch( $path, $user );
		$this->assertSame( array(), $a['plugin_urls'], "$path must not load any UI Kit file" );
		foreach ( array_merge( array_keys( $a['scripts'] ), array_keys( $a['styles'] ), array_keys( $a['inline'] ) ) as $handle ) {
			$this->assertDoesNotMatchRegularExpression( '#^acme-(ui|tabs|accordion|carousel)#', $handle, "$path must not load $handle" );
		}
		$this->assertStringNotContainsString( 'AcmeUIConfig', $html, "$path must not print the UI Kit config" );
	}

	/**
	 * Check a page that uses some components.
	 *
	 * @param string[] $components Components the page uses.
	 */
	protected function assertComponentAssets( string $path, array $components, bool $check_strategy = true ): array {
		list( $html, $a ) = $this->fetch( $path );
		$all = array( 'tabs', 'accordion', 'carousel' );
		$this->assertArrayHasKey( 'acme-ui-core', $a['scripts'], "$path: runtime (acme-ui-core) missing" );
		$this->assertArrayHasKey( 'acme-ui', $a['styles'], "$path: base styles (acme-ui) missing" );
		foreach ( $all as $c ) {
			if ( in_array( $c, $components, true ) ) {
				$this->assertArrayHasKey( "acme-$c-style", $a['styles'], "$path uses $c: acme-$c-style missing" );
			} else {
				$this->assertArrayNotHasKey( "acme-$c-style", $a['styles'], "$path doesn't use $c: acme-$c-style must not load" );
			}
		}
		$needs_icons = (bool) array_intersect( array( 'accordion', 'carousel' ), $components );
		$this->assertSame( $needs_icons, isset( $a['styles']['acme-ui-icons'] ), "$path: acme-ui-icons " . ( $needs_icons ? 'missing' : 'must not load' ) );
		$needs_motion = in_array( 'carousel', $components, true );
		$this->assertSame( $needs_motion, isset( $a['scripts']['acme-ui-motion'] ), "$path: acme-ui-motion " . ( $needs_motion ? 'missing' : 'must only load with a carousel' ) );
		$this->assertStringNotContainsString( 'acme-motion.min.js', $needs_motion ? '' : $html, "$path: the motion library must only load with a carousel" );

		// The config is printed before the runtime, the runtime before the components.
		$this->assertArrayHasKey( 'acme-ui-core-before', $a['inline'], "$path: AcmeUIConfig must be printed before acme-ui-core" );
		$this->assertLessThan( $a['scripts']['acme-ui-core']['pos'], $a['inline']['acme-ui-core-before'] );
		$this->assertStringContainsString( 'AcmeUIConfig', substr( $html, $a['inline']['acme-ui-core-before'], 600 ) );
		foreach ( kit_scripts( $a ) as $handle => $s ) {
			if ( 'acme-ui-core' !== $handle && 'acme-ui-motion' !== $handle ) {
				$this->assertGreaterThan( $a['scripts']['acme-ui-core']['pos'], $s['pos'], "$path: $handle is printed before acme-ui-core" );
			}
			if ( $check_strategy ) {
				$this->assertTrue( $s['defer'] || $s['async'], "$path: $handle must not block rendering: <script{$s['attrs']}>" );
			}
		}
		return array( $html, $a );
	}
}
