<?php
/**
 * Helpers for the product fields tests: read stored values and reproduce the exact requests the
 * admin screens send (forms are read from the real admin pages and serialized like a browser does).
 */

namespace WPSB\ProductFields;

function product_id( string $slug ): int {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'acme_product'", $slug ) );
}

/** Raw stored meta value (null when the key does not exist). */
function raw_meta( int $id, string $key ): ?string {
	global $wpdb;
	$wpdb->flush();
	return $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id DESC LIMIT 1", $id, $key ) );
}

/** Staff fields (not exposed in REST): raw strings. */
function staff_fields( int $id ): array {
	return array(
		'notes'    => raw_meta( $id, '_acme_internal_notes' ),
		'supplier' => raw_meta( $id, '_acme_supplier' ),
	);
}

function dom( string $html ): \DOMXPath {
	$doc = new \DOMDocument();
	$old = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?>' . $html );
	libxml_clear_errors();
	libxml_use_internal_errors( $old );
	return new \DOMXPath( $doc );
}

function text( \DOMNode $node ): string {
	return trim( preg_replace( '/\s+/u', ' ', $node->textContent ) );
}

/**
 * Successful form controls below the given nodes, in document order, as [name, value] pairs
 * (what a browser submits: unchecked boxes and buttons are skipped, selects submit their selected option).
 *
 * @param \DOMNode[] $roots
 */
function controls( \DOMXPath $xp, array $roots ): array {
	$pairs = array();
	foreach ( $roots as $root ) {
		foreach ( $xp->query( './/input | .//select | .//textarea', $root ) as $el ) {
			$name = $el->getAttribute( 'name' );
			if ( '' === $name || $el->hasAttribute( 'disabled' ) ) {
				continue;
			}
			$tag = strtolower( $el->nodeName );
			if ( 'input' === $tag ) {
				$type = strtolower( $el->getAttribute( 'type' ) ?: 'text' );
				if ( in_array( $type, array( 'submit', 'button', 'image', 'file', 'reset' ), true ) ) {
					continue;
				}
				if ( in_array( $type, array( 'checkbox', 'radio' ), true ) ) {
					if ( $el->hasAttribute( 'checked' ) ) {
						$pairs[] = array( $name, $el->hasAttribute( 'value' ) ? $el->getAttribute( 'value' ) : 'on' );
					}
					continue;
				}
				$pairs[] = array( $name, $el->getAttribute( 'value' ) );
			} elseif ( 'textarea' === $tag ) {
				$value   = $el->textContent;
				$pairs[] = array( $name, preg_replace( '/^\r?\n/', '', $value ) );
			} else {
				$options  = $xp->query( './/option', $el );
				$selected = null;
				foreach ( $options as $opt ) {
					if ( $opt->hasAttribute( 'selected' ) ) {
						$selected = $opt;
					}
				}
				if ( null === $selected && $options->length && ! $el->hasAttribute( 'multiple' ) ) {
					$selected = $options->item( 0 );
				}
				if ( null !== $selected ) {
					$pairs[] = array( $name, $selected->hasAttribute( 'value' ) ? $selected->getAttribute( 'value' ) : text( $selected ) );
				}
			}
		}
	}
	return $pairs;
}

/**
 * The form control labelled $label below $root (label[for] or a wrapping label).
 */
function control_by_label( \DOMXPath $xp, \DOMNode $root, string $label ): ?\DOMElement {
	foreach ( $xp->query( './/label', $root ) as $l ) {
		// Text of nested selects/textareas is not part of the label.
		$label_text = text( $l );
		foreach ( $xp->query( './/select | .//textarea', $l ) as $nested ) {
			$label_text = trim( str_replace( text( $nested ), '', $label_text ) );
		}
		if ( $label_text !== $label ) {
			continue;
		}
		if ( $l->getAttribute( 'for' ) ) {
			$el = $xp->query( "//*[@id='" . $l->getAttribute( 'for' ) . "']" )->item( 0 );
			if ( $el ) {
				return $el;
			}
		}
		$el = $xp->query( './/input | .//select | .//textarea', $l )->item( 0 );
		if ( $el ) {
			return $el;
		}
	}
	return null;
}

/** Replace (or remove, with null) every pair with the given name; append if missing. */
function set_pair( array $pairs, string $name, ?string $value ): array {
	$out   = array();
	$found = false;
	foreach ( $pairs as $p ) {
		if ( $p[0] === $name ) {
			if ( null !== $value && ! $found ) {
				$out[] = array( $name, $value );
			}
			$found = true;
			continue;
		}
		$out[] = $p;
	}
	if ( ! $found && null !== $value ) {
		$out[] = array( $name, $value );
	}
	return $out;
}

/** Set a labelled control like a user would (checkbox: bool; select: option text; others: string). */
function set_by_label( \DOMXPath $xp, \DOMNode $root, array $pairs, string $label, $value ): array {
	$el = control_by_label( $xp, $root, $label );
	if ( ! $el ) {
		throw new \RuntimeException( "No form control labelled '$label'" );
	}
	$name = $el->getAttribute( 'name' );
	$tag  = strtolower( $el->nodeName );
	if ( 'input' === $tag && 'checkbox' === strtolower( $el->getAttribute( 'type' ) ) ) {
		return set_pair( $pairs, $name, $value ? ( $el->hasAttribute( 'value' ) ? $el->getAttribute( 'value' ) : 'on' ) : null );
	}
	if ( 'select' === $tag ) {
		foreach ( $xp->query( './/option', $el ) as $opt ) {
			if ( text( $opt ) === $value ) {
				return set_pair( $pairs, $name, $opt->hasAttribute( 'value' ) ? $opt->getAttribute( 'value' ) : text( $opt ) );
			}
		}
		throw new \RuntimeException( "No option '$value' for '$label'" );
	}
	return set_pair( $pairs, $name, (string) $value );
}

function encode( array $pairs ): string {
	return implode( '&', array_map( static fn( $p ) => rawurlencode( $p[0] ) . '=' . rawurlencode( $p[1] ), $pairs ) );
}

function first( \DOMXPath $xp, string $query ): \DOMElement {
	$el = $xp->query( $query )->item( 0 );
	if ( ! $el ) {
		throw new \RuntimeException( "Nothing matches $query" );
	}
	return $el;
}

/**
 * Base class: HTTP flows as the admin screens run them.
 */
abstract class Base extends \WPSB\TestCase {

	protected bool $use_transactions = false;

	private static $logins = array();

	protected function login( int $user_id = 1 ): array {
		if ( ! isset( self::$logins[ $user_id ] ) ) {
			self::$logins[ $user_id ] = $this->http_login( $user_id );
		}
		return self::$logins[ $user_id ];
	}

	/** @var int[] Products created by the current test. */
	private array $fresh = array();

	protected function tearDown(): void {
		update_option( 'acme_pf_settings', array( 'currency' => '$', 'editor' => 'block' ) );
		foreach ( $this->fresh as $id ) {
			wp_delete_post( $id, true );
		}
		$this->fresh = array();
		parent::tearDown();
	}

	/**
	 * A fresh copy of a seeded product (same author, status and stored meta, byte for byte), so
	 * that every test starts from the seeded state.
	 */
	protected function fresh( string $slug ): int {
		global $wpdb;
		$source = get_post( product_id( $slug ) );
		$id     = wp_insert_post(
			array(
				'post_type'    => 'acme_product',
				'post_title'   => $source->post_title,
				'post_status'  => $source->post_status,
				'post_author'  => $source->post_author,
				'post_content' => '',
			),
			true
		);
		$this->assertIsInt( $id );
		$wpdb->update( $wpdb->posts, array( 'post_content' => $source->post_content ), array( 'ID' => $id ) );
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_id", $source->ID, '_acme%' ) ) as $row ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id, 'meta_key' => $row->meta_key, 'meta_value' => $row->meta_value ) );
		}
		clean_post_cache( $id );
		$this->fresh[] = $id;
		return $id;
	}

	protected function use_editor( string $editor ): void {
		update_option( 'acme_pf_settings', array( 'currency' => '$', 'editor' => $editor ) );
	}

	/** `meta` of the product as the REST API returns it to an editor (edit context). */
	protected function rest_meta( int $id ): array {
		wp_cache_flush();
		wp_set_current_user( 1 );
		$res = $this->rest( 'GET', '/wp/v2/acme_product/' . $id, array( 'context' => 'edit' ) );
		wp_set_current_user( 0 );
		$this->assertSame( 200, $res->get_status() );
		$data = $res->get_data();
		return (array) $data['meta'];
	}

	protected function get_page( string $path, int $user_id = 1 ): array {
		$page = $this->http( 'GET', $path, array( 'login' => $this->login( $user_id ) ) );
		$this->assertSame( 200, $page['status'], "GET $path" );
		return $page;
	}

	/**
	 * Open a product in the block editor: the meta box forms exactly as rendered on load (the editor
	 * keeps these values for all later saves unless the user edits the meta box) and the URL the
	 * editor posts them to after every save.
	 */
	protected function open_block_editor( int $id ): array {
		$page = $this->get_page( "/wp-admin/post.php?post=$id&action=edit" );
		$this->assertMatchesRegularExpression( '/_wpMetaBoxUrl\s*=\s*("[^"]+")/', $page['body'], 'Block editor page' );
		preg_match( '/_wpMetaBoxUrl\s*=\s*("[^"]+")/', $page['body'], $m );

		$xp    = dom( $page['body'] );
		$roots = array( first( $xp, "//form[contains(concat(' ', normalize-space(@class), ' '), ' metabox-base-form ')]" ) );
		foreach ( $xp->query( "//form[contains(@class, 'metabox-location-')]" ) as $form ) {
			if ( $xp->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), ' postbox ')]", $form )->length ) {
				$roots[] = $form;
			}
		}
		return array(
			'id'    => $id,
			'url'   => json_decode( $m[1] ),
			'xp'    => $xp,
			'pairs' => controls( $xp, $roots ),
		);
	}

	/**
	 * Edit fields in the meta box of an open block editor (labels => values).
	 */
	protected function edit_metabox( array $editor, array $changes ): array {
		foreach ( $changes as $label => $value ) {
			$editor['pairs'] = set_by_label( $editor['xp'], $editor['xp']->document->documentElement, $editor['pairs'], $label, $value );
		}
		return $editor;
	}

	/**
	 * The block editor's "Save"/"Update": the REST request with the edited meta (and other post
	 * fields), then the meta box request the editor sends right after it.
	 */
	protected function block_editor_save( array $editor, array $meta, array $extra = array() ): void {
		$id   = $editor['id'];
		$body = $extra;
		if ( $meta ) {
			$body['meta'] = $meta;
		}
		$rest = $this->http(
			'POST',
			'/wp-json/wp/v2/acme_product/' . $id,
			array(
				'login'      => $this->login(),
				'rest_nonce' => true,
				'json'       => true,
				'body'       => $body ? $body : array( 'title' => get_post( $id )->post_title ),
			)
		);
		$this->assertSame( 200, $rest['status'], 'REST save: ' . substr( $rest['body'], 0, 500 ) );

		$post    = get_post( $id );
		$pairs   = $editor['pairs'];
		$pairs[] = array( 'comment_status', $post->comment_status );
		$pairs[] = array( 'ping_status', $post->ping_status );
		$pairs[] = array( 'post_author', (string) $post->post_author );
		$mb      = $this->http(
			'POST',
			$editor['url'],
			array(
				'login'   => $this->login(),
				'body'    => encode( $pairs ),
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
		$this->assertContains( $mb['status'], array( 200, 302 ), 'Meta box request: ' . substr( $mb['body'], 0, 500 ) );
		wp_cache_flush();
	}

	/**
	 * Submit the classic editor form of a product ("Update").
	 *
	 * @param callable $edit fn( DOMXPath $xp, DOMNode $form, array $pairs ): array.
	 */
	protected function classic_save( int $id, callable $edit ): array {
		$page  = $this->get_page( "/wp-admin/post.php?post=$id&action=edit" );
		$xp    = dom( $page['body'] );
		$form  = first( $xp, "//form[@id='post']" );
		$pairs = $edit( $xp, $form, controls( $xp, array( $form ) ) );
		$pairs = set_pair( $pairs, 'save', 'Update' );
		$res   = $this->http(
			'POST',
			'/wp-admin/post.php',
			array(
				'login'   => $this->login(),
				'body'    => encode( $pairs ),
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
		$this->assertSame( 302, $res['status'], 'Classic editor save: ' . substr( $res['body'], 0, 500 ) );
		return $res;
	}

	/**
	 * Quick Edit of a product: the inline edit row filled like inline-edit-post.js does, then
	 * the user's changes to the product fields (labels => values), sent to admin-ajax.php.
	 */
	protected function quick_edit( int $id, array $changes, int $user_id = 1 ): array {
		$page = $this->get_page( '/wp-admin/edit.php?post_type=acme_product', $user_id );
		$xp   = dom( $page['body'] );
		$row  = first( $xp, "//tr[@id='inline-edit']" );
		$data = first( $xp, "//div[@id='inline_$id']" );

		$pairs = controls( $xp, array( $row ) );
		$value = static function ( string $class ) use ( $xp, $data ) {
			$el = $xp->query( ".//div[contains(concat(' ', normalize-space(@class), ' '), ' $class ')]", $data )->item( 0 );
			return $el ? html_entity_decode( $el->textContent, ENT_QUOTES ) : null;
		};
		foreach ( array( 'post_title', 'post_name', 'post_author', 'jj', 'mm', 'aa', 'hh', 'mn', 'ss', 'post_password' ) as $field ) {
			if ( null !== $value( $field ) ) {
				$pairs = set_pair( $pairs, $field, $value( $field ) );
			}
		}
		$status = $value( '_status' );
		$pairs  = set_pair( $pairs, '_status', $status );
		foreach ( array( 'comment_status', 'ping_status' ) as $field ) {
			$pairs = set_pair( $pairs, $field, 'open' === $value( $field ) ? 'open' : null );
		}

		// The product fields start with the product's current values (assets/quick-edit.js)...
		$pairs = $this->prefill_quick_edit( $xp, $row, $pairs, $id );
		// ... then the user changes them.
		foreach ( $changes as $label => $new ) {
			$pairs = set_by_label( $xp, $row, $pairs, $label, $new );
		}

		$pairs[] = array( 'action', 'inline-save' );
		$pairs[] = array( 'post_type', 'acme_product' );
		$pairs[] = array( 'post_ID', (string) $id );
		$pairs[] = array( 'edit_date', 'true' );
		$pairs[] = array( 'post_status', (string) $status );

		$res = $this->http(
			'POST',
			'/wp-admin/admin-ajax.php',
			array(
				'login'   => $this->login( $user_id ),
				'body'    => encode( $pairs ),
				'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
			)
		);
		return $res;
	}

	/** Current price / stock into the Quick Edit fields, as the list screen script does. */
	private function prefill_quick_edit( \DOMXPath $xp, \DOMNode $row, array $pairs, int $id ): array {
		$pairs = set_by_label( $xp, $row, $pairs, 'Price', (string) raw_meta( $id, '_acme_price' ) );
		return set_by_label( $xp, $row, $pairs, 'In stock', acme_pf_is_in_stock( $id ) );
	}

	/**
	 * Bulk Edit (the list screen's GET form) with the given product field choices (label => option text).
	 */
	protected function bulk_edit( array $ids, array $choices ): array {
		$login = $this->login();
		$page  = $this->get_page( '/wp-admin/edit.php?post_type=acme_product' );
		$xp    = dom( $page['body'] );
		$row   = first( $xp, "//tr[@id='bulk-edit']" );

		$pairs = array(
			array( 'post_type', 'acme_product' ),
			array( '_wpnonce', $this->nonce_for( 1, 'bulk-posts', $login['logged_in'] ) ),
			array( '_wp_http_referer', '/wp-admin/edit.php?post_type=acme_product' ),
			array( 'action', 'edit' ),
			array( 'screen', 'edit-acme_product' ),
		);
		// Defaults of the whole bulk edit row ("— No Change —" everywhere).
		foreach ( controls( $xp, array( $row ) ) as $p ) {
			$pairs[] = $p;
		}
		foreach ( $choices as $label => $option ) {
			$pairs = set_by_label( $xp, $row, $pairs, $label, $option );
		}
		foreach ( $ids as $id ) {
			$pairs[] = array( 'post[]', (string) $id );
		}
		$pairs[] = array( 'bulk_edit', 'Update' );
		$pairs[] = array( 'action2', '-1' );

		$res = $this->http( 'GET', '/wp-admin/edit.php?' . encode( $pairs ), array( 'login' => $login ) );
		$this->assertSame( 302, $res['status'], 'Bulk edit: ' . substr( $res['body'], 0, 300 ) );
		return $res;
	}
}
