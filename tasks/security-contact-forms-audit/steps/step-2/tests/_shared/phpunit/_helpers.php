<?php
/**
 * Helpers for the Acme Forms tests: fixture lookups, HTML form scraping, multipart bodies,
 * committed-state snapshots.
 */

namespace WPSB\Forms;

const FORMULA_ROWS = array( 'formula@example.org', 'formula2@example.org', 'formula3@example.org' );

function table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_form_submissions';
}

function user( string $login ): int {
	$u = get_user_by( 'login', $login );
	if ( ! $u ) {
		throw new \RuntimeException( "user $login not found" );
	}
	return (int) $u->ID;
}

function form_id( string $slug ): int {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'acme_form' AND post_name = %s", $slug ) );
	if ( ! $id ) {
		throw new \RuntimeException( "form $slug not found" );
	}
	return $id;
}

/** Raw submission row by (unique) e-mail. */
function by_email( string $email ): ?object {
	global $wpdb;
	$t = table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE email = %s ORDER BY id DESC LIMIT 1", $email ) ) ?: null;
}

function sub_id( string $email ): int {
	$row = by_email( $email );
	if ( ! $row ) {
		throw new \RuntimeException( "submission $email not found" );
	}
	return (int) $row->id;
}

function row( int $id ): ?object {
	global $wpdb;
	$t = table();
	return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", $id ) ) ?: null;
}

function count_rows( string $where = '1=1' ): int {
	global $wpdb;
	$t = table();
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE {$where}" );
}

function max_id(): int {
	global $wpdb;
	$t = table();
	return (int) $wpdb->get_var( "SELECT MAX(id) FROM {$t}" );
}

function audit_table(): string {
	global $wpdb;
	return $wpdb->prefix . 'acme_forms_audit_log';
}

function audit_table_exists(): bool {
	global $wpdb;
	$suppress = $wpdb->suppress_errors( true );
	$exists   = null !== $wpdb->get_var( 'SELECT COUNT(*) FROM ' . audit_table() );
	$wpdb->suppress_errors( $suppress );
	return $exists;
}

function audit_max_id(): int {
	global $wpdb;
	return audit_table_exists() ? (int) $wpdb->get_var( 'SELECT MAX(id) FROM ' . audit_table() ) : 0;
}

/** Audit entries with id > $after, oldest first, details decoded. */
function audit_since( int $after ): array {
	global $wpdb;
	$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . audit_table() . ' WHERE id > %d ORDER BY id ASC', $after ) );
	foreach ( $rows as $row ) {
		$row->details = json_decode( (string) $row->details, true );
	}
	return $rows;
}

/** Decoded field values of a submission row (JSON or 1.x serialized). */
function data_of( object $row ): array {
	$json = json_decode( (string) $row->data, true );
	if ( is_array( $json ) ) {
		return $json;
	}
	$legacy = @unserialize( (string) $row->data, array( 'allowed_classes' => false ) );
	return is_array( $legacy ) ? $legacy : array();
}

function files_of( object $row ): array {
	$json = json_decode( (string) $row->files, true );
	return is_array( $json ) ? $json : array();
}

function settings(): array {
	global $wpdb;
	$raw = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'acme_forms_settings'" );
	$val = maybe_unserialize( $raw );
	return is_array( $val ) ? $val : array();
}

/** XPath over an HTML document. */
function xpath( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_NOERROR | LIBXML_NOWARNING );
	libxml_clear_errors();
	return new \DOMXPath( $doc );
}

/** Absolute URL for an href/action found in an admin page. */
function absolute( string $href, string $base_path = '/wp-admin/' ): string {
	$href = html_entity_decode( $href, ENT_QUOTES | ENT_HTML5 );
	if ( preg_match( '#^https?://#i', $href ) ) {
		return $href;
	}
	if ( '' === $href ) {
		return rtrim( WP_HOME, '/' ) . $base_path;
	}
	if ( '/' === $href[0] ) {
		return rtrim( WP_HOME, '/' ) . $href;
	}
	if ( '?' === $href[0] ) {
		return rtrim( WP_HOME, '/' ) . preg_replace( '/\?.*$/', '', $base_path ) . $href;
	}
	return rtrim( WP_HOME, '/' ) . '/wp-admin/' . $href;
}

/**
 * Successful-control values of a <form> (like a browser would send them, without the submit
 * buttons). Repeated names ending in [] are collected into lists.
 */
function form_values( \DOMXPath $xp, \DOMElement $form ): array {
	$out = array();
	$add = static function ( string $name, string $value ) use ( &$out ) {
		if ( '[]' === substr( $name, -2 ) ) {
			$out[ $name ][] = $value;
		} else {
			$out[ $name ] = $value;
		}
	};
	foreach ( $xp->query( './/input[@name]', $form ) as $input ) {
		$type = strtolower( $input->getAttribute( 'type' ) ?: 'text' );
		if ( in_array( $type, array( 'submit', 'button', 'image', 'reset', 'file' ), true ) ) {
			continue;
		}
		if ( in_array( $type, array( 'checkbox', 'radio' ), true ) && ! $input->hasAttribute( 'checked' ) ) {
			continue;
		}
		$value = $input->hasAttribute( 'value' ) ? $input->getAttribute( 'value' ) : ( 'checkbox' === $type ? 'on' : '' );
		$add( $input->getAttribute( 'name' ), $value );
	}
	foreach ( $xp->query( './/textarea[@name]', $form ) as $ta ) {
		$add( $ta->getAttribute( 'name' ), $ta->textContent );
	}
	foreach ( $xp->query( './/select[@name]', $form ) as $select ) {
		$chosen = $xp->query( './/option[@selected]', $select )->item( 0 ) ?: $xp->query( './/option', $select )->item( 0 );
		if ( $chosen ) {
			$add( $select->getAttribute( 'name' ), $chosen->hasAttribute( 'value' ) ? $chosen->getAttribute( 'value' ) : trim( $chosen->textContent ) );
		}
	}
	return $out;
}

/** Encode form values (lists for names ending in []) as a query string. */
function encode( array $values ): string {
	$parts = array();
	foreach ( $values as $name => $value ) {
		foreach ( (array) $value as $v ) {
			$parts[] = rawurlencode( (string) $name ) . '=' . rawurlencode( (string) $v );
		}
	}
	return implode( '&', $parts );
}

/** Replace every token-looking value (10 hex chars, WordPress-style) with a forged one. */
function forge_tokens( array $values ): array {
	foreach ( $values as $name => $value ) {
		if ( is_string( $value ) && preg_match( '/^[a-f0-9]{10}$/', $value ) ) {
			$values[ $name ] = 'deadbeef00';
		}
	}
	return $values;
}

/** Same for a URL's query string. */
function forge_url_tokens( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['query'] ) ) {
		return $url;
	}
	parse_str( $parts['query'], $q );
	$q    = forge_tokens( $q );
	$base = strtok( $url, '?' );
	return $base . '?' . http_build_query( $q );
}

/** Drop every token-looking parameter from a URL. */
function strip_url_tokens( string $url ): string {
	$parts = wp_parse_url( $url );
	if ( empty( $parts['query'] ) ) {
		return $url;
	}
	parse_str( $parts['query'], $q );
	foreach ( $q as $k => $v ) {
		if ( is_string( $v ) && preg_match( '/^[a-f0-9]{10}$/', $v ) ) {
			unset( $q[ $k ] );
		}
	}
	return strtok( $url, '?' ) . '?' . http_build_query( $q );
}

/**
 * multipart/form-data body. $files: field => [filename, content, mime].
 *
 * @return array{0:string,1:string} body, content type header.
 */
function multipart( array $fields, array $files = array() ): array {
	$boundary = '----wpsb' . md5( uniqid( '', true ) );
	$body     = '';
	$flat     = array();
	foreach ( $fields as $name => $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$flat[] = array( "{$name}[{$k}]", (string) $v );
			}
		} else {
			$flat[] = array( (string) $name, (string) $value );
		}
	}
	foreach ( $flat as $pair ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$pair[0]}\"\r\n\r\n{$pair[1]}\r\n";
	}
	foreach ( $files as $name => $file ) {
		$body .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"{$name}\"; filename=\"{$file[0]}\"\r\nContent-Type: {$file[2]}\r\n\r\n{$file[1]}\r\n";
	}
	$body .= "--{$boundary}--\r\n";
	return array( $body, 'multipart/form-data; boundary=' . $boundary );
}

/** Parse a CSV document into rows. */
function parse_csv( string $csv ): array {
	$fh = fopen( 'php://memory', 'r+' );
	fwrite( $fh, $csv );
	rewind( $fh );
	$rows = array();
	while ( false !== ( $row = fgetcsv( $fh, null, ',', '"', '' ) ) ) {
		if ( array( null ) === $row ) {
			continue;
		}
		$rows[] = $row;
	}
	fclose( $fh );
	return $rows;
}

/** Uploaded files below uploads/acme-forms (path => md5). */
function upload_files(): array {
	$dir = WP_CONTENT_DIR . '/uploads/acme-forms';
	$out = array();
	if ( ! is_dir( $dir ) ) {
		return $out;
	}
	$it = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ) );
	foreach ( $it as $file ) {
		// Ignore folder protection files (.htaccess, index.php, …) a plugin may add.
		if ( $file->isFile() && '.' !== $file->getFilename()[0] && ! preg_match( '/^index\.(php|html?)$/', $file->getFilename() ) ) {
			$out[ $file->getPathname() ] = md5_file( $file->getPathname() );
		}
	}
	return $out;
}

function pdf_bytes( string $marker ): string {
	return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [] /Count 0 >> endobj\n% {$marker}\ntrailer << /Root 1 0 R >>\n%%EOF\n";
}

function png_bytes(): string {
	return base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==' );
}

function jpeg_bytes(): string {
	return base64_decode( '/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=' );
}

/**
 * Base for tests that talk to the Playground server: committed data, restored after each test.
 */
abstract class HttpCase extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	private array $backup_rows     = array();
	private $backup_settings       = null;
	private array $backup_files    = array();
	private array $logins          = array();
	private int $debug_log_size    = 0;
	protected int $audit_before    = 0;

	protected function setUp(): void {
		parent::setUp();
		global $wpdb;
		$this->backup_rows     = $wpdb->get_results( 'SELECT * FROM ' . table() . ' ORDER BY id', ARRAY_A );
		$this->backup_settings = $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = 'acme_forms_settings'" );
		$this->backup_files    = upload_files();
		$this->audit_before    = audit_max_id();
		$log                   = WP_CONTENT_DIR . '/debug.log';
		$this->debug_log_size  = is_file( $log ) ? filesize( $log ) : 0;
		$this->clear_mails();
	}

	protected function tearDown(): void {
		global $wpdb;
		if ( audit_table_exists() ) {
			$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . audit_table() . ' WHERE id > %d', $this->audit_before ) );
		}
		$wpdb->query( 'DELETE FROM ' . table() );
		foreach ( $this->backup_rows as $row ) {
			$wpdb->insert( table(), $row );
		}
		$wpdb->update( $wpdb->options, array( 'option_value' => $this->backup_settings ), array( 'option_name' => 'acme_forms_settings' ) );
		foreach ( array_keys( array_diff_key( upload_files(), $this->backup_files ) ) as $path ) {
			@unlink( $path );
		}
		wp_cache_flush();
		parent::tearDown();
	}

	/** Logged-in session for a user (cached per test). */
	protected function session( string $login ): array {
		if ( ! isset( $this->logins[ $login ] ) ) {
			$this->logins[ $login ] = $this->http_login( user( $login ) );
		}
		return $this->logins[ $login ];
	}

	/** GET an admin URL (path relative to the site root, or absolute) as a user (null = logged out). */
	protected function get( ?string $login, string $path ): array {
		return $this->http( 'GET', $path, $login ? array( 'login' => $this->session( $login ) ) : array() );
	}

	/** Submit scraped form values like a browser. */
	protected function submit( ?string $login, string $method, string $url, array $values ): array {
		$opts = $login ? array( 'login' => $this->session( $login ) ) : array();
		if ( 'GET' === strtoupper( $method ) ) {
			$url = strtok( $url, '?' ) . '?' . encode( $values );
			return $this->http( 'GET', $url, $opts );
		}
		$opts['headers'] = array( 'Content-Type' => 'application/x-www-form-urlencoded' );
		$opts['body']    = encode( $values );
		return $this->http( 'POST', $url, $opts );
	}

	/** The inbox list page (optionally with query args). */
	protected function inbox( string $login, array $args = array() ): array {
		$res = $this->get( $login, '/wp-admin/admin.php?' . http_build_query( array( 'page' => 'acme-forms-submissions' ) + $args ) );
		$this->assertSame( 200, $res['status'], "inbox as $login: " . substr( $res['body'], 0, 300 ) );
		return $res;
	}

	protected function view( string $login, int $id ): array {
		$res = $this->get( $login, '/wp-admin/admin.php?page=acme-forms-submissions&action=view&submission=' . $id );
		$this->assertSame( 200, $res['status'], "view $id as $login" );
		return $res;
	}

	/** The row "Delete" link of a submission in list HTML. */
	protected function row_delete_link( string $html, int $id ): string {
		$xp    = xpath( $html );
		$links = $xp->query( "//tr[.//input[@type='checkbox' and @value='{$id}']]//a[normalize-space(.)='Delete']" );
		$this->assertGreaterThan( 0, $links->length, "no Delete row action for submission $id" );
		return absolute( $links->item( 0 )->getAttribute( 'href' ) );
	}

	/** The list form (the one with the row checkboxes) and its values. */
	protected function list_form( string $html ): array {
		$xp   = xpath( $html );
		$form = $xp->query( "//form[.//input[@type='checkbox' and @name='submission[]']]" )->item( 0 );
		$this->assertNotNull( $form, 'list form with row checkboxes not found' );
		$values = form_values( $xp, $form );
		unset( $values['submission[]'] );
		// Choose "Delete" in the first bulk action dropdown.
		$select = $xp->query( ".//select[.//option[normalize-space(.)='Delete']]", $form )->item( 0 );
		$this->assertNotNull( $select, 'bulk actions dropdown with Delete not found' );
		$option = $xp->query( ".//option[normalize-space(.)='Delete']", $select )->item( 0 );
		$values[ $select->getAttribute( 'name' ) ] = $option->getAttribute( 'value' );
		return array(
			'method' => strtoupper( $form->getAttribute( 'method' ) ?: 'GET' ),
			'action' => absolute( $form->getAttribute( 'action' ), '/wp-admin/admin.php' ),
			'values' => $values,
		);
	}

	/** Admin-post form on a page, identified by one of its field names. */
	protected function find_form( string $html, string $xpath_condition, string $base = '/wp-admin/admin.php' ): array {
		$xp   = xpath( $html );
		$form = $xp->query( "//form[{$xpath_condition}]" )->item( 0 );
		$this->assertNotNull( $form, "form [$xpath_condition] not found" );
		return array(
			'method' => strtoupper( $form->getAttribute( 'method' ) ?: 'GET' ),
			'action' => absolute( $form->getAttribute( 'action' ), $base ),
			'values' => form_values( $xp, $form ),
		);
	}

	/** The export form's values (from the inbox) merged with $args. */
	protected function export( string $login, array $args, ?array $form = null ): array {
		if ( null === $form ) {
			$form = $this->export_form( $login );
		}
		return $this->submit( $login, $form['method'], $form['action'], array_merge( $form['values'], $args ) );
	}

	protected function export_form( string $login ): array {
		return $this->find_form( $this->inbox( $login )['body'], ".//input[@name='action' and @value='acme_forms_export']" );
	}

	/** Public form submission (logged out, like a visitor). */
	protected function submit_public( string $slug, array $fields, array $files = array(), ?string $login = null ): array {
		$page = '/' . ( 'job-application' === $slug ? 'careers' : 'contact' ) . '/';
		list( $body, $type ) = multipart(
			array(
				'action'       => 'acme_forms_submit',
				'acme_form_id' => form_id( $slug ),
				'acme_return'  => rtrim( WP_HOME, '/' ) . $page,
				'acme_hp'      => '',
				'acme_fields'  => $fields,
			),
			$files
		);
		$opts = array(
			'headers' => array( 'Content-Type' => $type ),
			'body'    => $body,
		);
		if ( $login ) {
			$opts['login'] = $this->session( $login );
		}
		return $this->http( 'POST', '/wp-admin/admin-post.php', $opts );
	}

	protected function assertRedirectStatus( array $res, string $status ): void {
		$this->assertContains( $res['status'], array( 302, 303 ), 'expected a redirect back to the form, got ' . $res['status'] . ' ' . substr( $res['body'], 0, 300 ) );
		$this->assertStringContainsString( 'acme_form=' . $status, $res['headers']['location'] ?? '' );
	}

	/** Mails captured since setUp whose subject contains $needle. */
	protected function mails_about( string $needle ): array {
		return array_values( array_filter( $this->mails(), static fn( $m ) => false !== strpos( (string) $m['subject'], $needle ) ) );
	}

	/** New debug.log lines since setUp. */
	protected function new_debug_log(): string {
		$log = WP_CONTENT_DIR . '/debug.log';
		if ( ! is_file( $log ) ) {
			return '';
		}
		clearstatcache();
		return (string) file_get_contents( $log, false, null, $this->debug_log_size );
	}

	/** Short-message substring assertions (don't dump whole admin pages into the log). */
	protected function assertAbsent( string $needle, string $haystack, string $message ): void {
		$pos = stripos( $haystack, $needle );
		$ctx = false === $pos ? '' : ' … ' . substr( $haystack, max( 0, $pos - 80 ), 200 );
		$this->assertFalse( false !== $pos, "$message: found '$needle'$ctx" );
	}

	protected function assertPresent( string $needle, string $haystack, string $message ): void {
		$this->assertTrue( false !== strpos( $haystack, $needle ), "$message: '$needle' not found" );
	}

	/** No submission data in a response body. */
	protected function assertNoSubmissionData( array $res, string $what ): void {
		foreach ( array( 'visitor', 'mallory@', 'anna@', 'Anna Andersson', '203.0.113.' ) as $needle ) {
			$this->assertAbsent( $needle, $res['body'], "$what leaks submission data" );
		}
	}
}
