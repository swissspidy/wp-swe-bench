<?php
/**
 * acme_migrate_replace(): the public single-value API (used by the Pro importer and deploy scripts).
 */

use function WPSB\Migrate\wakeup_log;
use const WPSB\Migrate\NEW_URL;
use const WPSB\Migrate\OLD_URL;

class ReplaceValueTest extends WPSB\TestCase {

	protected function setUp(): void {
		parent::setUp();
		@unlink( wakeup_log() );
	}

	private static function s( string $value ): string {
		return 's:' . strlen( $value ) . ':"' . $value . '";';
	}

	private function replace( $value, string $search = OLD_URL, string $replace = NEW_URL ): array {
		$count  = null;
		$result = acme_migrate_replace( $value, $search, $replace, $count );
		return array( $result, $count );
	}

	public function test_plain_strings_are_replaced_and_counted(): void {
		list( $out, $count ) = $this->replace( 'Go to ' . OLD_URL . '/shop/ or ' . OLD_URL . '.' );
		$this->assertSame( 'Go to ' . NEW_URL . '/shop/ or ' . NEW_URL . '.', $out );
		$this->assertSame( 2, $count );

		list( $out, $count ) = $this->replace( 'Nothing to see at https://elsewhere.example/' );
		$this->assertSame( 'Nothing to see at https://elsewhere.example/', $out );
		$this->assertSame( 0, $count );
	}

	public function test_search_is_literal_and_case_sensitive(): void {
		list( $out, $count ) = $this->replace( 'old-shopXtest and old-shop.test and OLD-SHOP.TEST', 'old-shop.test', 'shop.acme.example' );
		$this->assertSame( 'old-shopXtest and shop.acme.example and OLD-SHOP.TEST', $out );
		$this->assertSame( 1, $count );
	}

	public function test_replacement_containing_the_search_string_is_not_replaced_again(): void {
		$in                  = serialize( array( 'host' => 'acme.test', 'url' => 'https://acme.test/a/', 'json' => '{"u":"https:\/\/acme.test\/"}' ) );
		list( $out, $count ) = $this->replace( $in, 'acme.test', 'www.acme.test' );
		$this->assertSame( serialize( array( 'host' => 'www.acme.test', 'url' => 'https://www.acme.test/a/', 'json' => '{"u":"https:\/\/www.acme.test\/"}' ) ), $out );
		$this->assertSame( 3, $count );

		list( $out, $count ) = $this->replace( 'See acme.test.', 'acme.test', 'www.acme.test' );
		$this->assertSame( 'See www.acme.test.', $out );
		$this->assertSame( 1, $count );
	}

	public function test_serialized_arrays_keep_valid_lengths_including_multibyte(): void {
		$in                  = serialize( array( 'city' => 'Zürich', 'url' => OLD_URL . '/zürich/', 'list' => array( 1, 2.5, true, null, OLD_URL ) ) );
		list( $out, $count ) = $this->replace( $in );
		$this->assertSame( serialize( array( 'city' => 'Zürich', 'url' => NEW_URL . '/zürich/', 'list' => array( 1, 2.5, true, null, NEW_URL ) ) ), $out );
		$this->assertSame( 2, $count );
	}

	public function test_values_without_a_match_are_returned_byte_for_byte(): void {
		$values = array(
			'a:3:{s:1:"f";d:0.1;s:1:"n";i:-42;s:1:"p";s:10:"C:\\x\\"y\\"z";}',
			'<!-- wp:acme/x {"a":"\\u003cb\\u003e \\u0026 \\u0022"} /-->',
			'b:0;',
			'plain text',
		);
		foreach ( $values as $value ) {
			list( $out, $count ) = $this->replace( $value );
			$this->assertSame( $value, $out );
			$this->assertSame( 0, $count );
		}
	}

	public function test_serialized_strings_inside_serialized_data_are_rewritten_with_correct_lengths(): void {
		$inner               = serialize( array( 'src' => OLD_URL . '/a.jpg', 'deeper' => serialize( array( OLD_URL . '/b/' ) ) ) );
		$in                  = serialize( array( 'v' => 2, 'data' => $inner ) );
		list( $out, $count ) = $this->replace( $in );
		$expected_inner      = serialize( array( 'src' => NEW_URL . '/a.jpg', 'deeper' => serialize( array( NEW_URL . '/b/' ) ) ) );
		$this->assertSame( serialize( array( 'v' => 2, 'data' => $expected_inner ) ), $out );
		$this->assertSame( 2, $count );
		$this->assertIsArray( unserialize( unserialize( $out )['data'] ) );
	}

	public function test_backslashes_and_quotes_in_serialized_data_survive(): void {
		$data                = array( 'path' => 'C:\\Shop\\"exports"', 'json' => '{"u":"' . str_replace( '/', '\\/', OLD_URL ) . '\\/x"}', 'url' => OLD_URL );
		list( $out, $count ) = $this->replace( serialize( $data ) );
		$this->assertSame( serialize( array( 'path' => 'C:\\Shop\\"exports"', 'json' => '{"u":"' . str_replace( '/', '\\/', NEW_URL ) . '\\/x"}', 'url' => NEW_URL ) ), $out );
		$this->assertSame( 2, $count );
	}

	public function test_json_escaped_urls_are_replaced_keeping_the_escaping(): void {
		$in                  = '<!-- wp:acme/map {"pins":[{"url":"http:\/\/old-shop.test\/stores\/zurich\/"}],"home":"http://old-shop.test/"} /-->';
		list( $out, $count ) = $this->replace( $in );
		$this->assertSame( '<!-- wp:acme/map {"pins":[{"url":"https:\/\/shop.acme.example\/stores\/zurich\/"}],"home":"https://shop.acme.example/"} /-->', $out );
		$this->assertSame( 2, $count );
	}

	public function test_url_encoded_urls_are_replaced_keeping_the_encoding(): void {
		$in                  = 'https://social.example/share?u=http%3A%2F%2Fold-shop.test%2Fsale%2F&t=Sale';
		list( $out, $count ) = $this->replace( $in );
		$this->assertSame( 'https://social.example/share?u=https%3A%2F%2Fshop.acme.example%2Fsale%2F&t=Sale', $out );
		$this->assertSame( 1, $count );

		$in                  = serialize( array( 'share' => $in ) );
		list( $out, $count ) = $this->replace( $in );
		$this->assertSame( serialize( array( 'share' => 'https://social.example/share?u=https%3A%2F%2Fshop.acme.example%2Fsale%2F&t=Sale' ) ), $out );
		$this->assertSame( 1, $count );
	}

	public function test_std_class_objects_are_rewritten(): void {
		$o                   = new stdClass();
		$o->canonical        = OLD_URL . '/p/';
		$o->nested           = array( 'img' => OLD_URL . '/i.png' );
		list( $out, $count ) = $this->replace( serialize( $o ) );
		$e                   = new stdClass();
		$e->canonical        = NEW_URL . '/p/';
		$e->nested           = array( 'img' => NEW_URL . '/i.png' );
		$this->assertSame( serialize( $e ), $out );
		$this->assertSame( 2, $count );
	}

	public function test_objects_of_classes_that_are_not_loaded_are_kept_intact(): void {
		$this->assertFalse( class_exists( 'Acme_Gone_Widget', false ) );
		$props = function ( string $u ) {
			return self::s( 'title' ) . self::s( 'Promo "big"' )
				. self::s( "\0*\0link" ) . self::s( $u . '/promo/' )
				. self::s( "\0Acme_Gone_Widget\0image" ) . self::s( $u . '/wp-content/uploads/p.jpg' )
				. self::s( 'items' ) . 'a:1:{i:0;' . self::s( $u ) . '}';
		};
		$in       = 'a:2:{i:0;O:16:"Acme_Gone_Widget":4:{' . $props( OLD_URL ) . '}s:4:"more";b:1;}';
		$expected = 'a:2:{i:0;O:16:"Acme_Gone_Widget":4:{' . $props( NEW_URL ) . '}s:4:"more";b:1;}';

		list( $out, $count ) = $this->replace( $in );
		$this->assertSame( $expected, $out );
		$this->assertSame( 3, $count );
		$this->assertFalse( class_exists( 'Acme_Gone_Widget', false ) );
	}

	public function test_stored_objects_are_never_instantiated(): void {
		$item          = new Acme_Legacy_Cache_Item();
		$item->key     = 'hero';
		$item->payload = array( 'img' => OLD_URL . '/hero.jpg' );
		$item->expires = 0;
		$in            = serialize( array( 'cached' => $item ) );

		list( $out, $count ) = $this->replace( $in );

		$item->payload = array( 'img' => NEW_URL . '/hero.jpg' );
		$this->assertSame( serialize( array( 'cached' => $item ) ), $out );
		$this->assertSame( 1, $count );
		$this->assertFileDoesNotExist( wakeup_log(), 'A stored Acme_Legacy_Cache_Item was instantiated (its __wakeup() ran) while replacing' );
	}
}
