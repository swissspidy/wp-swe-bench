<?php
/**
 * Importing without "Change all imported URLs…" keeps the content exactly as exported.
 */

use function WPSB\Importer\import_command;
use function WPSB\Importer\imported;
use function WPSB\Importer\original;
use function WPSB\Importer\posts;
use const WPSB\Importer\FIXTURES;

class ImportVerbatimTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	public function test_import_without_rewriting_keeps_content_verbatim(): void {
		$res = $this->wp_cli( import_command( FIXTURES . '/alpine-trails-full.xml', false ) );
		$this->assertSame( 0, $res['exit'], $res['stderr'] . $res['stdout'] );
		wp_cache_flush();
		foreach ( array_keys( posts() ) as $slug ) {
			$this->assertSame( original( $slug ), imported( $slug ), "Content of $slug" );
		}
	}
}
