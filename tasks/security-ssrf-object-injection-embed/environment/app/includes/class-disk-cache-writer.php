<?php
/**
 * Buffered writer used by the optional static HTML cache.
 *
 * Preview cards can be flushed to a file on disk (e.g. for a CDN origin). The
 * writer collects the markup and writes it out when it goes out of scope, so a
 * caller does not have to remember to flush.
 *
 * @package Acme\LinkPreviews
 */

namespace Acme\LinkPreviews;

defined( 'ABSPATH' ) || exit;

/**
 * Writes buffered HTML to a file on destruction.
 */
class Disk_Cache_Writer {

	/**
	 * Target file path.
	 *
	 * @var string
	 */
	public $path = '';

	/**
	 * Markup to write.
	 *
	 * @var string
	 */
	public $contents = '';

	/**
	 * Whether there is unwritten content.
	 *
	 * @var bool
	 */
	public $dirty = false;

	/**
	 * Constructor.
	 *
	 * @param string $path Target path.
	 */
	public function __construct( $path = '' ) {
		$this->path = $path;
	}

	/**
	 * Queue markup for writing.
	 *
	 * @param string $contents Markup.
	 */
	public function write( $contents ) {
		$this->contents = $contents;
		$this->dirty    = true;
	}

	/**
	 * Flush to disk when the object is destroyed.
	 */
	public function __destruct() {
		if ( $this->dirty && '' !== $this->path ) {
			file_put_contents( $this->path, $this->contents );
			$this->dirty = false;
		}
	}
}
