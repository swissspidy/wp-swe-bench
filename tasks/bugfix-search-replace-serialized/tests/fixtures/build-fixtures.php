<?php
/**
 * Builds fixtures.json (seeded rows + the exact values expected after
 * `search-replace http://old-shop.test https://shop.acme.example`).
 *
 * Expected values are built independently of any replacement code: every fixture is a template
 * (or a data structure) rendered once with the old URL tokens and once with the new ones, then
 * serialized with PHP's own serialize(). Run with any PHP >= 7.4: php build-fixtures.php > fixtures.json
 */

// phpcs:ignoreFile

// Classes only used to produce serialized objects. The site does not define Acme_Old_Slider_Slide.
class Acme_Old_Slider_Slide {
	public $title;
	public $image;
	public $link;
	public $settings;
}
class Acme_Legacy_Cache_Item {
	public $key;
	public $payload;
	public $expires;
}

const OLD = 'http://old-shop.test';
const NEW_URL = 'https://shop.acme.example';

function tokens( $new ) {
	$u = $new ? NEW_URL : OLD;
	return array(
		'{U}'  => $u,
		'{UJ}' => str_replace( '/', '\\/', $u ),
		'{UE}' => rawurlencode( $u ),
	);
}

function render( $tpl, $new ) {
	return strtr( $tpl, tokens( $new ) );
}

/** Occurrences of the old URL in any supported form. */
function occurrences( $s ) {
	$t = tokens( false );
	return substr_count( $s, $t['{U}'] ) + substr_count( $s, $t['{UJ}'] ) + substr_count( $s, $t['{UE}'] );
}

/** Fixture from a template string. */
function tpl_fixture( $key, $where, $tpl ) {
	$in = render( $tpl, false );
	return array( 'key' => $key ) + $where + array(
		'input'    => $in,
		'expected' => render( $tpl, true ),
		'count'    => occurrences( $in ),
	);
}

/** Fixture from a data builder fn( array $tokens ) => mixed, stored serialized. */
function data_fixture( $key, $where, $builder ) {
	$in = serialize( $builder( tokens( false ) ) );
	return array( 'key' => $key ) + $where + array(
		'input'    => $in,
		'expected' => serialize( $builder( tokens( true ) ) ),
		'count'    => occurrences( $in ),
	);
}

function slide( $t, $title ) {
	$s           = new Acme_Old_Slider_Slide();
	$s->title    = $title;
	$s->image    = $t['{U}'] . '/wp-content/uploads/2023/11/slide-' . strtolower( $title ) . '.jpg';
	$s->link     = array( 'href' => $t['{U}'] . '/sale/', 'target' => '_blank' );
	$s->settings = array( 'autoplay' => true, 'delay' => 4.5, 'order' => -1 );
	return $s;
}

$fixtures = array();

// ----- wp_posts -----------------------------------------------------------------------------
$fixtures[] = tpl_fixture(
	'post-classic-html',
	array( 'table' => 'posts', 'column' => 'post_content', 'post_name' => 'classic-shop-page', 'post_type' => 'page' ),
	"<p>Visit <a href=\"{U}/shop/\">our shop</a> or write to us via {U}/contact/.</p>\n<p><img src=\"{U}/wp-content/uploads/2024/03/team.jpg\" alt=\"The team\" /></p>"
);
$fixtures[] = tpl_fixture(
	'post-classic-html-excerpt',
	array( 'table' => 'posts', 'column' => 'post_excerpt', 'post_name' => 'classic-shop-page', 'post_type' => 'page' ),
	'Our shop moved to {U}/shop/ - come and see.'
);
$fixtures[] = tpl_fixture(
	'post-blocks',
	array( 'table' => 'posts', 'column' => 'post_content', 'post_name' => 'summer-sale', 'post_type' => 'post' ),
	"<!-- wp:acme/promo {\"title\":\"Summer \\u003cb\\u003esale\\u003c/b\\u003e \\u0026 more\",\"url\":\"{U}/summer/\",\"note\":\"\\u0022Up to 50%\\u0022 \\u002d\\u002d while stocks last\"} /-->\n\n"
	. "<!-- wp:image {\"id\":41,\"sizeSlug\":\"large\",\"linkDestination\":\"custom\"} -->\n<figure class=\"wp-block-image size-large\"><a href=\"{U}/summer/\"><img src=\"{U}/wp-content/uploads/2024/06/summer.jpg\" alt=\"Summer sale\" class=\"wp-image-41\"/></a></figure>\n<!-- /wp:image -->\n\n"
	. "<!-- wp:buttons -->\n<div class=\"wp-block-buttons\"><!-- wp:button {\"className\":\"is-style-fill\"} -->\n<div class=\"wp-block-button is-style-fill\"><a class=\"wp-block-button__link wp-element-button\" href=\"{U}/cart/?add=12\\u0026qty=1\">Buy now</a></div>\n<!-- /wp:button --></div>\n<!-- /wp:buttons -->\n\n"
	. "<!-- wp:acme/store-map {\"markers\":[{\"label\":\"Z\\u00fcrich\",\"url\":\"{UJ}\\/stores\\/zurich\\/\"},{\"label\":\"Bern\",\"url\":\"{UJ}\\/stores\\/bern\\/\"}],\"regex\":\"^\\\\d{4}$\"} /-->\n\n"
	. "<!-- wp:paragraph -->\n<p>Share: <a href=\"https://social.example/share?u={UE}%2Fsummer%2F\">Social</a> · <code>C:\\Shop\\exports</code></p>\n<!-- /wp:paragraph -->"
);
$fixtures[] = tpl_fixture(
	'post-guid',
	array( 'table' => 'posts', 'column' => 'guid', 'post_name' => 'summer-sale', 'post_type' => 'post', 'guid' => true ),
	'{U}/?p=4711'
);
$fixtures[] = tpl_fixture(
	'template-index',
	array( 'table' => 'posts', 'column' => 'post_content', 'post_name' => 'index', 'post_type' => 'wp_template' ),
	"<!-- wp:template-part {\"slug\":\"header\",\"tagName\":\"header\"} /-->\n\n"
	. "<!-- wp:group {\"tagName\":\"main\",\"style\":{\"background\":{\"backgroundImage\":{\"url\":\"{UJ}\\/wp-content\\/uploads\\/2024\\/01\\/bg.png\",\"source\":\"file\"}}}} -->\n<main class=\"wp-block-group\"><!-- wp:navigation {\"overlayMenu\":\"never\"} -->\n"
	. "<!-- wp:navigation-link {\"label\":\"Shop \\u0026 Sale\",\"url\":\"{UJ}\\/shop\\/\",\"kind\":\"custom\"} /-->\n"
	. "<!-- wp:navigation-link {\"label\":\"\\u003cem\\u003eJournal\\u003c/em\\u003e\",\"url\":\"{U}/journal/\",\"kind\":\"custom\"} /-->\n<!-- /wp:navigation -->\n\n"
	. "<!-- wp:query {\"queryId\":1,\"query\":{\"perPage\":3,\"postType\":\"post\"}} -->\n<div class=\"wp-block-query\"><!-- wp:post-template -->\n<!-- wp:post-title {\"isLink\":true} /-->\n<!-- /wp:post-template --></div>\n<!-- /wp:query --></main>\n<!-- /wp:group -->\n\n"
	. "<!-- wp:template-part {\"slug\":\"footer\",\"tagName\":\"footer\"} /-->"
);
$fixtures[] = tpl_fixture(
	'post-no-match',
	array( 'table' => 'posts', 'column' => 'post_content', 'post_name' => 'about-us', 'post_type' => 'page' ),
	"<!-- wp:paragraph -->\n<p>We are on <a href=\"https://old-shop.test.example.org/\">a different host</a> and {\\\"quoted\\\"} \\\\server\\share.</p>\n<!-- /wp:paragraph -->"
);

// ----- wp_postmeta ----------------------------------------------------------------------------
$fixtures[] = data_fixture(
	'meta-nested-array',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'summer-sale', 'post_type' => 'post', 'meta_key' => '_acme_banner' ),
	static function ( $t ) {
		return array(
			'image'   => $t['{U}'] . '/wp-content/uploads/2024/06/banner.jpg',
			'cta'     => array(
				'label' => 'Shop "now" \\ save',
				'links' => array( $t['{U}'] . '/summer/', 'https://partner.example/?ref=' . $t['{UE}'], 'C:\\Shop\\banner.psd' ),
			),
			'visible' => true,
			'weight'  => 1.5,
			7         => null,
		);
	}
);
$fixtures[] = data_fixture(
	'meta-double-serialized',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'summer-sale', 'post_type' => 'post', 'meta_key' => '_acme_builder_data' ),
	static function ( $t ) {
		return array(
			'version' => 3,
			'layout'  => serialize(
				array(
					'rows' => array(
						array( 'type' => 'image', 'src' => $t['{U}'] . '/wp-content/uploads/2024/06/row.jpg' ),
						array( 'type' => 'html', 'html' => '<a href="' . $t['{U}'] . '/faq/">FAQ</a> — Zürich' ),
					),
				)
			),
		);
	}
);
$fixtures[] = data_fixture(
	'meta-stdclass',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'summer-sale', 'post_type' => 'post', 'meta_key' => '_acme_seo' ),
	static function ( $t ) {
		$o            = new stdClass();
		$o->canonical = $t['{U}'] . '/summer-sale/';
		$o->og        = new stdClass();
		$o->og->image = $t['{U}'] . '/wp-content/uploads/2024/06/og.jpg';
		$o->og->title = 'Summer sale – up to 50%';
		$o->noindex   = false;
		return $o;
	}
);
$fixtures[] = data_fixture(
	'meta-unknown-class',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'home', 'post_type' => 'page', 'meta_key' => '_old_slider_slides' ),
	static function ( $t ) {
		return array( slide( $t, 'Spring' ), slide( $t, 'Summer' ) );
	}
);
$fixtures[] = data_fixture(
	'meta-cache-object',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'home', 'post_type' => 'page', 'meta_key' => '_acme_legacy_cache' ),
	static function ( $t ) {
		$c          = new Acme_Legacy_Cache_Item();
		$c->key     = 'home-hero';
		$c->payload = array( 'html' => '<img src="' . $t['{U}'] . '/wp-content/uploads/hero.jpg">', 'json' => '{"src":"' . $t['{UJ}'] . '\\/hero.jpg"}' );
		$c->expires = 1735689600;
		return $c;
	}
);
$fixtures[] = tpl_fixture(
	'meta-json',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'home', 'post_type' => 'page', 'meta_key' => '_acme_hero_json' ),
	'{"src":"{UJ}\\/wp-content\\/uploads\\/2024\\/02\\/hero.webp","alt":"Caf\\u00e9 \\"Acme\\"","links":["{UJ}\\/menu\\/","{UJ}\\/booking\\/"]}'
);
$fixtures[] = data_fixture(
	'meta-json-in-serialized',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'home', 'post_type' => 'page', 'meta_key' => '_acme_widgets_config' ),
	static function ( $t ) {
		return array(
			'map'   => '{"center":[47.37,8.54],"pin":"' . $t['{UJ}'] . '\\/pin.svg"}',
			'share' => 'https://social.example/share?u=' . $t['{UE}'] . '%2F&title=Acme',
		);
	}
);
$fixtures[] = data_fixture(
	'meta-no-match',
	array( 'table' => 'postmeta', 'column' => 'meta_value', 'post_name' => 'home', 'post_type' => 'page', 'meta_key' => '_acme_untouched' ),
	static function ( $t ) {
		return array( 'path' => 'C:\\data\\"quoted"', 'n' => -3, 'f' => 0.25, 'ok' => false, 'host' => 'old-shop.test' );
	}
);

// ----- wp_options -------------------------------------------------------------------------------
$fixtures[] = data_fixture(
	'option-widget-text',
	array( 'table' => 'options', 'column' => 'option_value', 'option_name' => 'widget_text', 'autoload' => 'on' ),
	static function ( $t ) {
		return array(
			2              => array( 'title' => 'Opening hours', 'text' => 'See <a href="' . $t['{U}'] . '/hours/">our hours</a>.', 'filter' => true, 'visual' => true ),
			3              => array( 'title' => 'Newsletter', 'text' => '<form action="' . $t['{U}'] . '/subscribe/">\\n</form>', 'filter' => false ),
			'_multiwidget' => 1,
		);
	}
);
$fixtures[] = data_fixture(
	'option-cache-object',
	array( 'table' => 'options', 'column' => 'option_value', 'option_name' => 'acme_legacy_menu_cache', 'autoload' => 'off' ),
	static function ( $t ) {
		$c          = new Acme_Legacy_Cache_Item();
		$c->key     = 'main-menu';
		$c->payload = array( $t['{U}'] . '/shop/', $t['{U}'] . '/journal/' );
		$c->expires = 1735689600;
		return array( 'items' => array( $c ), 'built' => '2024-12-01' );
	}
);
$fixtures[] = tpl_fixture(
	'option-json',
	array( 'table' => 'options', 'column' => 'option_value', 'option_name' => 'acme_shop_endpoints', 'autoload' => 'on' ),
	'{"api":"{UJ}\\/wp-json\\/acme\\/v1\\/","webhook":"{UJ}\\/?acme-hook=1","legacy":"{U}/api.php"}'
);

// ----- other core tables ----------------------------------------------------------------------
$fixtures[] = tpl_fixture(
	'term-description',
	array( 'table' => 'term_taxonomy', 'column' => 'description', 'term_slug' => 'news', 'taxonomy' => 'category' ),
	'Company news. Archive: <a href="{U}/category/news/">{U}/category/news/</a>'
);
$fixtures[] = data_fixture(
	'termmeta-image',
	array( 'table' => 'termmeta', 'column' => 'meta_value', 'term_slug' => 'news', 'taxonomy' => 'category', 'meta_key' => 'acme_term_image' ),
	static function ( $t ) {
		return array( 'url' => $t['{U}'] . '/wp-content/uploads/news.png', 'width' => 1200 );
	}
);
$fixtures[] = tpl_fixture(
	'user-url',
	array( 'table' => 'users', 'column' => 'user_url', 'user_login' => 'shopkeeper' ),
	'{U}/team/anna/'
);
$fixtures[] = tpl_fixture(
	'usermeta-description',
	array( 'table' => 'usermeta', 'column' => 'meta_value', 'user_login' => 'shopkeeper', 'meta_key' => 'description' ),
	'Anna runs the shop. Portfolio: {U}/team/anna/ "since 2012"'
);
$fixtures[] = tpl_fixture(
	'comment-author-url',
	array( 'table' => 'comments', 'column' => 'comment_author_url', 'comment_author' => 'Ben' ),
	'{U}/customers/ben/'
);
$fixtures[] = tpl_fixture(
	'comment-content',
	array( 'table' => 'comments', 'column' => 'comment_content', 'comment_author' => 'Ben' ),
	'Great sale! Link for friends: {U}/summer/?utm_source=comment'
);
$fixtures[] = tpl_fixture(
	'redirect-plain',
	array( 'table' => 'acme_redirects', 'column' => 'target', 'source' => '/old-summer/' ),
	'{U}/summer/'
);
$fixtures[] = tpl_fixture(
	'redirect-encoded',
	array( 'table' => 'acme_redirects', 'column' => 'target', 'source' => '/go/partner/' ),
	'https://partner.example/track?to={UE}%2Fshop%2F'
);

echo json_encode( $fixtures, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ), "\n";
