<?php
/**
 * Tools → Import → WordPress, as the site owner did it: upload the export, keep the default options.
 */

use function WPSB\Importer\cover_background;
use function WPSB\Importer\find_blocks;
use function WPSB\Importer\imported;
use function WPSB\Importer\original;
use function WPSB\Importer\self_closing;
use function WPSB\Importer\shape;
use const WPSB\Importer\SITE;

class AdminImportTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	/** multipart/form-data POST with the login cookies. */
	private function upload( array $login, string $path, array $fields, string $file_field, string $file ): array {
		$ch = curl_init( rtrim( WP_HOME, '/' ) . $path );
		$fields[ $file_field ] = new CURLFile( $file, 'text/xml', basename( $file ) );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $fields,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HTTPHEADER     => array( 'Cookie: ' . $login['cookie'] ),
				CURLOPT_TIMEOUT        => 300,
				CURLOPT_PROXY          => '',
			)
		);
		$body   = (string) curl_exec( $ch );
		$status = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
		curl_close( $ch );
		return array( 'status' => $status, 'body' => $body );
	}

	public function test_import_from_the_admin_screen_with_default_options(): void {
		$login = $this->http_login( 1 );
		$page  = $this->http( 'GET', '/wp-admin/admin.php?import=wordpress', array( 'login' => $login ) );
		$this->assertSame( 200, $page['status'] );
		$this->assertMatchesRegularExpression( '/id="import-upload-form"[^>]*action="([^"]+)"/', $page['body'] );
		preg_match( '/id="import-upload-form"[^>]*action="([^"]+)"/', $page['body'], $m );

		$step1 = $this->upload(
			$login,
			'/wp-admin/' . html_entity_decode( $m[1] ),
			array(
				'action'        => 'save',
				'max_file_size' => '2097152000',
			),
			'import',
			'/root/alpine-trails-export.xml'
		);
		$this->assertSame( 200, $step1['status'] );
		$this->assertMatchesRegularExpression( '/name="import_id" value="(\d+)"/', $step1['body'], substr( wp_strip_all_tags( $step1['body'] ), 0, 800 ) );
		preg_match( '/name="import_id" value="(\d+)"/', $step1['body'], $id );
		preg_match( '/name="_wpnonce" value="([a-f0-9]+)"/', $step1['body'], $nonce );
		// The URL option is checked by default.
		$this->assertMatchesRegularExpression( '/<input[^>]+name="rewrite_urls"[^>]+checked/', $step1['body'] );

		$step2 = $this->http(
			'POST',
			'/wp-admin/admin.php?import=wordpress&step=2',
			array(
				'login' => $login,
				'body'  => array(
					'_wpnonce'            => $nonce[1],
					'_wp_http_referer'    => '/wp-admin/admin.php?import=wordpress&step=1',
					'import_id'           => $id[1],
					'imported_authors'    => array( 'trailadmin' ),
					'user_map'            => array( '1' ),
					'user_new'            => array( '' ),
					'rewrite_urls'        => '1',
				),
			)
		);
		$this->assertSame( 200, $step2['status'] );
		$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( preg_replace( '#<(script|style)\b.*?</\1>#s', '', $step2['body'] ) ) );
		$this->assertStringContainsString( 'All done', $text, substr( $text, strpos( $text, 'Import WordPress' ) ?: 0, 1500 ) );
		wp_cache_flush();

		foreach ( array( 'main-menu', 'follow-us', 'summer-in-the-alps', 'packing-tips' ) as $slug ) {
			$this->assertSame( shape( parse_blocks( original( $slug ) ) ), shape( parse_blocks( imported( $slug ) ) ), "Block structure of $slug" );
			$this->assertSame( self_closing( original( $slug ) ), self_closing( imported( $slug ) ), "Self-closing blocks in $slug" );
			$this->assertStringNotContainsString( 'https://oldblog.example', imported( $slug ), "Old URLs left in $slug" );
		}
		$cover = find_blocks( parse_blocks( imported( 'summer-in-the-alps' ) ), 'core/cover' )[0];
		$this->assertSame( SITE . '/wp-content/uploads/2024/05/alps-hero.jpg', $cover['attrs']['url'] );
		$this->assertSame( $cover['attrs']['url'], cover_background( $cover ) );
	}
}
