<?php
/**
 * Plugin Name: Acme site customizations
 * Description: Events post type and the Mastodon share network for the Acme site.
 */

add_action(
	'init',
	static function () {
		register_post_type(
			'event',
			array(
				'label'        => 'Events',
				'public'       => true,
				'has_archive'  => true,
				'show_in_rest' => true,
				'supports'     => array( 'title', 'editor', 'excerpt', 'thumbnail' ),
				'rewrite'      => array( 'slug' => 'events' ),
			)
		);
	}
);

add_filter(
	'acme_social_networks',
	static function ( $networks ) {
		$networks['mastodon'] = array(
			'label'     => 'Mastodon',
			'share_url' => 'https://mastodonshare.com/?url=%1$s&text=%2$s',
		);
		return $networks;
	}
);
