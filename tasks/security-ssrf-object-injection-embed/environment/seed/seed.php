<?php
/**
 * Seed the preview cache for Acme Link Previews.
 *
 * @package Acme\LinkPreviews
 */

use function Acme\LinkPreviews\cache_key;

$cache = array();

// A normal, benign cached preview.
$cache[ cache_key( 'https://example.com/hello' ) ] = array(
	'url'         => 'https://example.com/hello',
	'title'       => 'Hello from Example',
	'description' => 'A friendly example page.',
	'image'       => 'https://example.com/img/hello.png',
);

// A cached preview whose fields came from an attacker-controlled remote page
// (this is what the site stored before the fix). It must render safely.
$cache[ cache_key( 'https://evil.example/post' ) ] = array(
	'url'         => 'https://evil.example/post',
	'title'       => 'Great deal <script>alert(1)</script>',
	'description' => 'Buy now <img src=x onerror=alert(2)> limited time',
	'image'       => 'javascript:alert(3)',
);

update_option( 'acme_lp_cache', $cache );

echo "Seeded " . count( $cache ) . " cached previews.\n";
