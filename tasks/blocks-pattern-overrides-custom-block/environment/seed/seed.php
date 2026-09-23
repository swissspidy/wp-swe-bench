<?php
/**
 * Seed content: synced patterns with CTAs, pages using them with overrides, standalone and 1.x CTAs.
 */

wp_set_current_user( 1 );
$dir = __DIR__ . '/posts/';

$mk = static function ( $slug, $type, $title, $content ) {
	$id = wp_insert_post(
		array(
			'post_type'    => $type,
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => wp_slash( $content ),
			'post_author'  => 1,
		),
		true
	);
	if ( is_wp_error( $id ) ) {
		fwrite( STDERR, $id->get_error_message() );
		exit( 1 );
	}
	return $id;
};

$instance = static function ( $ref, $content = null ) {
	$attrs = array( 'ref' => $ref );
	if ( null !== $content ) {
		$attrs['content'] = $content;
	}
	return serialize_block(
		array(
			'blockName'    => 'core/block',
			'attrs'        => $attrs,
			'innerBlocks'  => array(),
			'innerContent' => array(),
		)
	);
};

$para = static function ( $text ) {
	return "<!-- wp:paragraph -->\n<p>$text</p>\n<!-- /wp:paragraph -->";
};

$newsletter = $mk( 'newsletter-signup', 'wp_block', 'Newsletter signup', file_get_contents( $dir . 'pattern-newsletter.html' ) );
$webinar    = $mk( 'webinar-cta', 'wp_block', 'Webinar', file_get_contents( $dir . 'pattern-webinar.html' ) );
$sales      = $mk( 'sales-cta', 'wp_block', 'Talk to sales', file_get_contents( $dir . 'pattern-sales.html' ) );

$mk(
	'pricing',
	'page',
	'Pricing',
	implode(
		"\n\n",
		array(
			$para( 'Simple pricing for teams of every size.' ),
			$instance(
				$newsletter,
				array(
					'Intro'          => array( 'content' => 'Prices change every quarter.' ),
					'Newsletter CTA' => array(
						'heading'    => 'Get the <em>pricing</em> digest',
						'buttonText' => 'Send me prices',
						'buttonUrl'  => 'https://news.example.com/pricing',
					),
				)
			),
			$para( 'Enterprise plans are billed yearly.' ),
			$instance( $newsletter, array( 'Newsletter CTA' => array( 'heading' => 'Only the heading changed' ) ) ),
			$para( 'Questions? Read the FAQ.' ),
			$instance( $newsletter ),
			$instance( $sales ),
		)
	)
);

$mk(
	'webinars',
	'page',
	'Webinars',
	implode(
		"\n\n",
		array(
			$para( 'Upcoming and past webinars.' ),
			$instance(
				$webinar,
				array(
					'Webinar CTA' => array(
						'heading'    => 'Webinar replay: <strong>patterns</strong>',
						'buttonText' => 'Watch the replay',
						'buttonUrl'  => 'https://events.example.com/replay',
					),
				)
			),
			$instance( $webinar ),
		)
	)
);

// Overrides that were imported from an old staging site: stored data we must not trust.
$mk(
	'cta-override-probe',
	'post',
	'Imported landing page',
	implode(
		"\n\n",
		array(
			$para( 'Imported from staging.' ),
			$instance(
				$newsletter,
				array(
					'Intro'          => array( 'content' => 'Imported intro' ),
					'Newsletter CTA' => array(
						'heading'       => 'Hello <img src=x onerror=alert(1)><script>alert(2)</script>world',
						'buttonText'    => 'Click <script>alert(3)</script>me',
						'buttonUrl'     => 'javascript:alert(document.cookie)',
						'variant'       => 'dark" onclick="alert(4)',
						'campaign'      => 'hijacked',
						'opensInNewTab' => true,
						'headingLevel'  => 1,
					),
				)
			),
			$instance(
				$newsletter,
				array(
					'Newsletter CTA' => array(
						'buttonUrl' => 'https://news.example.com/"><script>alert(5)</script>',
						'variant'   => 'dark',
					),
				)
			),
		)
	)
);

$mk( 'standalone-ctas', 'post', 'Standalone CTAs', file_get_contents( $dir . 'standalone-ctas.html' ) );
$mk( 'legacy-cta', 'post', 'Legacy CTA', file_get_contents( $dir . 'legacy-cta.html' ) );
$mk(
	'newsletter-everywhere',
	'post',
	'Newsletter everywhere',
	$para( 'A post that just uses the pattern.' ) . "\n\n" . $instance( $newsletter )
);

delete_transient( 'acme_cta_inventory' );
echo "seeded\n";
