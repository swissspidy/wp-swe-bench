<?php
/**
 * Helpers for the Acme Link Previews security tests.
 */

namespace WPSB\LinkPreviews;

/**
 * Build a PHP-serialized object string by hand, WITHOUT instantiating the class
 * (so no destructor ever runs inside the test itself).
 *
 * @param string               $class Fully-qualified class name.
 * @param array<string, mixed> $props Public properties.
 * @return string
 */
function serialized_object( string $class, array $props ): string {
	$out = 'O:' . strlen( $class ) . ':"' . $class . '":' . count( $props ) . ':{';
	foreach ( $props as $name => $value ) {
		$out .= serialized_value( (string) $name ) . serialized_value( $value );
	}
	return $out . '}';
}

/**
 * Serialize a scalar value the way PHP's serialize() would.
 *
 * @param mixed $value Value.
 * @return string
 */
function serialized_value( $value ): string {
	if ( is_bool( $value ) ) {
		return 'b:' . ( $value ? '1' : '0' ) . ';';
	}
	if ( is_int( $value ) ) {
		return 'i:' . $value . ';';
	}
	$value = (string) $value;
	return 's:' . strlen( $value ) . ':"' . $value . '";';
}

/**
 * A base64 payload that, if unserialized as an object, would write a file to
 * disk via the Disk_Cache_Writer gadget's destructor.
 *
 * @param string $path     Target path the gadget would write to.
 * @param string $contents Contents the gadget would write.
 * @return string
 */
function gadget_cookie( string $path, string $contents ): string {
	$ser = serialized_object(
		'Acme\\LinkPreviews\\Disk_Cache_Writer',
		array(
			'path'     => $path,
			'contents' => $contents,
			'dirty'    => true,
		)
	);
	return base64_encode( $ser );
}

/**
 * A base64 payload of a serialized ARRAY that contains the gadget object as a
 * value (what a tampered export file looks like).
 *
 * @param string $path     Target path the gadget would write to.
 * @param string $contents Contents the gadget would write.
 * @return string
 */
function gadget_import( string $path, string $contents ): string {
	$obj = serialized_object(
		'Acme\\LinkPreviews\\Disk_Cache_Writer',
		array(
			'path'     => $path,
			'contents' => $contents,
			'dirty'    => true,
		)
	);
	$array = 'a:1:{' . serialized_value( 'pwn' ) . $obj . '}';
	return base64_encode( $array );
}
