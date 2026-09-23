<?php
/**
 * The header and footer served by the real server show the same content as 3.4.1,
 * from each site's data (menus, widgets, Customizer settings).
 */

use function WPSB\Corp\dom;
use function WPSB\Corp\footer_html;
use function WPSB\Corp\header_html;
use function WPSB\Corp\hrefs;
use function WPSB\Corp\link_by_text;
use function WPSB\Corp\menu_id;
use function WPSB\Corp\nav_links;
use function WPSB\Corp\norm_url;
use function WPSB\Corp\text;

class HeaderFooterParityTest extends WPSB\TestCase {

	protected bool $use_transactions = false;

	private $saved_mods;
	private $saved_blogname;
	private $saved_sidebars;
	private array $cleanup_items = array();
	private array $restore_items = array();

	protected function setUp(): void {
		parent::setUp();
		$this->saved_mods     = get_option( 'theme_mods_acme-corporate' );
		$this->saved_blogname = get_option( 'blogname' );
		$this->saved_sidebars = get_option( 'sidebars_widgets' );
	}

	protected function tearDown(): void {
		update_option( 'theme_mods_acme-corporate', $this->saved_mods );
		update_option( 'blogname', $this->saved_blogname );
		update_option( 'sidebars_widgets', $this->saved_sidebars );
		foreach ( $this->restore_items as list( $menu, $item ) ) {
			wp_update_nav_menu_item(
				$menu,
				$item->ID,
				array(
					'menu-item-title'    => $item->title,
					'menu-item-url'      => $item->url,
					'menu-item-type'     => 'custom',
					'menu-item-status'   => 'publish',
					'menu-item-position' => $item->menu_order,
				)
			);
		}
		foreach ( $this->cleanup_items as $id ) {
			wp_delete_post( $id, true );
		}
		foreach ( get_posts( array( 'post_type' => array( 'wp_template_part', 'wp_navigation' ), 'post_status' => 'any', 'numberposts' => -1 ) ) as $p ) {
			wp_delete_post( $p->ID, true );
		}
		parent::tearDown();
	}

	private function get( string $path ): string {
		$res = $this->http( 'GET', $path );
		$this->assertContains( $res['status'], array( 200, 404 ), "GET $path" );
		$this->assertStringNotContainsString( 'Fatal error', $res['body'] );
		return $res['body'];
	}

	private function h( string $path ): string {
		return rtrim( home_url(), '/' ) . $path;
	}

	private function expected_primary(): array {
		return array(
			array( 'Home', $this->h( '/' ) ),
			array( 'About', $this->h( '/about/' ) ),
			array( 'Team', $this->h( '/about/team/' ) ),
			array( 'Services', $this->h( '/services/' ) ),
			array( 'Careers', 'https://jobs.acme-corp.example/' ),
			array( 'Contact', $this->h( '/contact/' ) ),
		);
	}

	private function expected_footer_nav(): array {
		return array(
			array( 'Privacy Policy', $this->h( '/privacy-policy/' ) ),
			array( 'Imprint', $this->h( '/imprint/' ) ),
			array( 'Get in touch', $this->h( '/contact/' ) ),
		);
	}

	private function assertHeader( string $page ): void {
		$header = header_html( $page );
		$x      = dom( $header );

		$title = link_by_text( $header, 'Acme Corporation' );
		$this->assertNotNull( $title, 'Site title link missing in the header' );
		$this->assertSame( $this->h( '/' ), norm_url( $title->getAttribute( 'href' ) ) );
		$this->assertStringContainsString( 'Engineering since 1953', text( $x->query( '//body' )->item( 0 ) ), 'Tagline missing' );

		$this->assertSame( $this->expected_primary(), nav_links( $header ), 'Primary navigation must match the Primary menu' );

		// Team stays a submenu of About.
		$team = link_by_text( $header, 'Team' );
		$this->assertNotNull( $team );
		$nested = false;
		for ( $n = $team->parentNode; $n instanceof DOMElement; $n = $n->parentNode ) {
			if ( 'li' === strtolower( $n->nodeName ) && $n !== $team->parentNode ) {
				$nested = null !== link_by_text( $n->ownerDocument->saveHTML( $n ), 'About' );
				break;
			}
		}
		$this->assertTrue( $nested, 'Team must be rendered as a submenu item of About' );

		$cta = link_by_text( $header, 'Request a quote' );
		$this->assertNotNull( $cta, 'Header button missing' );
		$this->assertSame( $this->h( '/contact/' ), norm_url( $cta->getAttribute( 'href' ) ) );
	}

	private function assertFooter( string $page ): void {
		$footer = footer_html( $page );
		$body   = text( dom( $footer )->query( '//body' )->item( 0 ) );

		$this->assertStringContainsString( 'Head office', $body, 'Footer widget area missing' );
		$this->assertStringContainsString( '221 Example Street', $body );

		$this->assertSame( $this->expected_footer_nav(), nav_links( $footer ), 'Footer navigation must match the Footer menu' );

		$links = hrefs( $footer );
		$this->assertContains( 'tel:+15550102030', $links );
		$this->assertContains( 'mailto:hello@acme-corp.example', $links );
		$this->assertStringContainsString( '+1 (555) 010-2030', $body );

		foreach ( array( 'https://www.linkedin.com/company/acme-corp', 'https://github.com/acme-corp', 'https://twitter.com/acmecorp' ) as $profile ) {
			$this->assertContains( $profile, $links, "Social profile $profile missing" );
		}
		foreach ( $links as $href ) {
			$this->assertStringStartsNotWith( 'javascript:', strtolower( trim( $href ) ) );
		}
		$this->assertStringNotContainsString( 'javascript:', strtolower( $page ) );
		$this->assertStringNotContainsString( 'youtube', strtolower( implode( ' ', $links ) ) );

		$year = gmdate( 'Y' );
		$this->assertStringContainsString( "© $year Acme Corporation. All rights reserved. Privacy", $body );
		$privacy = link_by_text( $footer, 'Privacy' );
		$this->assertNotNull( $privacy );
		$this->assertSame( $this->h( '/privacy-policy/' ), norm_url( $privacy->getAttribute( 'href' ) ) );
		$this->assertStringNotContainsString( '{year}', $page );
	}

	/**
	 * @dataProvider provide_pages
	 */
	public function test_header_and_footer_on_every_kind_of_page( string $path ): void {
		$page = $this->get( $path );
		$this->assertHeader( $page );
		$this->assertFooter( $page );
		$this->assertStringContainsString( 'href="#primary"', $page, 'Skip link must still work' );
	}

	public static function provide_pages(): array {
		return array(
			'front page' => array( '/' ),
			'page'       => array( '/about/' ),
			'child page' => array( '/about/team/' ),
			'post'       => array( '/new-plant-opens/' ),
			'blog index' => array( '/news/' ),
			'search'     => array( '/?s=plant' ),
			'404'        => array( '/does-not-exist-at-all/' ),
		);
	}

	public function test_menu_changes_in_appearance_menus_show_up(): void {
		$main = menu_id( 'Main menu' );
		$id   = wp_update_nav_menu_item(
			$main,
			0,
			array(
				'menu-item-title'    => 'Investors',
				'menu-item-url'      => 'https://ir.acme-corp.example/',
				'menu-item-status'   => 'publish',
				'menu-item-type'     => 'custom',
				'menu-item-position' => 99,
			)
		);
		$this->assertIsInt( $id );
		$this->cleanup_items[] = $id;

		$footer_menu = menu_id( 'Footer links' );
		$items       = wp_get_nav_menu_items( $footer_menu );
		$imprint     = null;
		foreach ( $items as $item ) {
			if ( 'Imprint' === $item->title ) {
				$imprint = $item;
			}
		}
		$this->assertNotNull( $imprint );
		$this->restore_items[] = array( $footer_menu, $imprint );
		wp_update_nav_menu_item(
			$footer_menu,
			$imprint->ID,
			array(
				'menu-item-title'    => 'Legal notice',
				'menu-item-url'      => $imprint->url,
				'menu-item-type'     => 'custom',
				'menu-item-status'   => 'publish',
				'menu-item-position' => $imprint->menu_order,
			)
		);

		$page = $this->get( '/about/' );
		$expected   = $this->expected_primary();
		$expected[] = array( 'Investors', 'https://ir.acme-corp.example/' );
		$this->assertSame( $expected, nav_links( header_html( $page ) ) );
		$footer = $this->expected_footer_nav();
		$footer[1][0] = 'Legal notice';
		$this->assertSame( $footer, nav_links( footer_html( $page ) ) );

	}

	public function test_without_assigned_menus(): void {
		$mods = get_option( 'theme_mods_acme-corporate' );
		$mods['nav_menu_locations'] = array();
		update_option( 'theme_mods_acme-corporate', $mods );

		$page   = $this->get( '/services/' );
		$header = nav_links( header_html( $page ) );
		$urls   = array_column( $header, 1 );
		foreach ( array( '/about/', '/services/', '/contact/', '/news/', '/privacy-policy/' ) as $path ) {
			$this->assertContains( $this->h( $path ), $urls, "Page list fallback must link to $path" );
		}
		$this->assertNotContains( 'https://jobs.acme-corp.example/', $urls );
		$this->assertSame( array(), nav_links( footer_html( $page ) ), 'No footer navigation without a Footer menu' );
		// Everything else is still there.
		$footer = text( dom( footer_html( $page ) )->query( '//body' )->item( 0 ) );
		$this->assertStringContainsString( 'All rights reserved.', $footer );
	}

	public function test_other_sites_settings_are_used(): void {
		// A regional site: different Customizer settings, no header button, tagline off.
		update_option( 'blogname', 'Acme Europe' );
		$mods = get_option( 'theme_mods_acme-corporate' );
		$mods['acme_corporate_show_tagline']      = false;
		$mods['acme_corporate_header_cta_label']  = 'Kontakt';
		$mods['acme_corporate_header_cta_url']    = 'https://acme-corp.example/de/kontakt/';
		$mods['acme_corporate_footer_text']       = 'Made by {site} in {year}. <em>Proudly</em> <script>alert(1)</script>';
		$mods['acme_corporate_social_links']      = array( 'facebook' => 'https://facebook.com/acme.eu', 'linkedin' => '' );
		$mods['acme_corporate_twitter_url']       = '';
		$mods['acme_corporate_youtube_url']       = 'https://youtube.com/@acme-eu';
		$mods['acme_corporate_contact_phone']     = '';
		$mods['acme_corporate_contact_email']     = 'eu@acme-corp.example';
		update_option( 'theme_mods_acme-corporate', $mods );

		$page   = $this->get( '/about/' );
		$header = header_html( $page );
		$this->assertNotNull( link_by_text( $header, 'Acme Europe' ), 'Site title must follow the site settings' );
		$this->assertStringNotContainsString( 'Engineering since 1953', $header, 'Tagline is switched off on this site' );
		$cta = link_by_text( $header, 'Kontakt' );
		$this->assertNotNull( $cta );
		$this->assertSame( 'https://acme-corp.example/de/kontakt/', $cta->getAttribute( 'href' ) );
		$this->assertNull( link_by_text( $header, 'Request a quote' ) );

		$footer = footer_html( $page );
		$body   = text( dom( $footer )->query( '//body' )->item( 0 ) );
		$this->assertStringContainsString( 'Made by Acme Europe in ' . gmdate( 'Y' ) . '. Proudly', $body );
		$this->assertStringContainsString( '<em>Proudly</em>', $footer );
		$this->assertStringNotContainsString( '<script>alert(1)', $footer );
		$links = hrefs( $footer );
		$this->assertContains( 'https://facebook.com/acme.eu', $links );
		$this->assertContains( 'https://youtube.com/@acme-eu', $links );
		$this->assertNotContains( 'https://github.com/acme-corp', $links );
		$this->assertNotContains( 'https://www.linkedin.com/company/acme-corp', $links );
		$this->assertNotContains( 'https://twitter.com/acmecorp', $links );
		$this->assertContains( 'mailto:eu@acme-corp.example', $links );
		foreach ( $links as $href ) {
			$this->assertStringStartsNotWith( 'tel:', $href, 'No phone configured on this site' );
		}

		// Header button removed entirely.
		$mods['acme_corporate_header_cta_label'] = '';
		update_option( 'theme_mods_acme-corporate', $mods );
		$page = $this->get( '/about/' );
		$this->assertNull( link_by_text( header_html( $page ), 'Kontakt' ) );
	}

	public function test_footer_widget_area_changes_show_up(): void {
		$widgets = get_option( 'widget_text' );
		$saved   = $widgets;
		$widgets[1]['text'] = 'Acme Corporation<br>1 New Plaza<br>Springfield';
		update_option( 'widget_text', $widgets );
		try {
			$footer = text( dom( footer_html( $this->get( '/about/' ) ) )->query( '//body' )->item( 0 ) );
		} finally {
			update_option( 'widget_text', $saved );
		}
		$this->assertStringContainsString( '1 New Plaza', $footer );
		$this->assertStringNotContainsString( '221 Example Street', $footer );
	}
}
