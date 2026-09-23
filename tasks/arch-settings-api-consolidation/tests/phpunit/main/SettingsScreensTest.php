<?php
/**
 * The three settings screens: rendering, saving, validation, legacy reads.
 */

class SettingsScreensTest extends AcmeSocialCase {

	const SCREENS = array(
		'acme-social'          => array(
			'labels' => array( 'Enable share buttons', 'Networks', 'Button position', 'Show on', 'Button style' ),
			'keys'   => array( 'share_enabled', 'networks][', 'position', 'post_types][', 'button_style' ),
		),
		'acme-social-profiles' => array(
			'labels' => array( 'Twitter/X username', 'Facebook page URL', 'Instagram URL', 'LinkedIn URL', 'YouTube channel URL' ),
			'keys'   => array( 'twitter', 'facebook', 'instagram', 'linkedin', 'youtube' ),
		),
		'acme-social-og'       => array(
			'labels' => array( 'Enable Open Graph tags', 'Default share image (attachment ID)', 'Facebook App ID', 'Twitter card type' ),
			'keys'   => array( 'og_enabled', 'og_default_image', 'fb_app_id', 'twitter_card' ),
		),
	);

	public function test_screens_keep_their_urls_and_labels(): void {
		foreach ( self::SCREENS as $slug => $screen ) {
			$res = $this->http( 'GET', self::screen( $slug ), array( 'login' => $this->login( 'admin' ) ) );
			$this->assertSame( 200, $res['status'], $slug );
			foreach ( $screen['labels'] as $label ) {
				$this->assertStringContainsString( esc_html( $label ), $res['body'], "$slug: label $label" );
			}
		}
	}

	public function test_screens_edit_the_consolidated_setting(): void {
		foreach ( self::SCREENS as $slug => $screen ) {
			$form  = $this->get_form( self::screen( $slug ), $this->login( 'admin' ) );
			$names = $this->field_names( $form );
			foreach ( $screen['keys'] as $key ) {
				$this->assertContains( 'acme_social_settings[' . $key . ']', $names, "$slug: field for $key" );
			}
			$own = array_map( static fn( $k ) => explode( ']', $k )[0], $screen['keys'] );
			foreach ( $names as $name ) {
				if ( str_starts_with( $name, 'acme_social_settings[' ) ) {
					$key = explode( ']', substr( $name, strlen( 'acme_social_settings[' ) ) )[0];
					$this->assertContains( $key, $own, "$slug must only contain its own fields, found $name" );
				}
			}
		}

		// Current values are shown, checkbox groups offer registered networks/post types.
		$form = $this->get_form( self::screen( 'acme-social' ), $this->login( 'admin' ) );
		$networks = $this->checkbox_values( $form, 'acme_social_settings[networks][]' );
		foreach ( array( 'facebook', 'twitter', 'linkedin', 'pinterest', 'whatsapp', 'email', 'mastodon' ) as $network ) {
			$this->assertContains( $network, $networks );
		}
		$types = $this->checkbox_values( $form, 'acme_social_settings[post_types][]' );
		foreach ( array( 'post', 'page', 'event' ) as $type ) {
			$this->assertContains( $type, $types );
		}
		$checked = array_column( array_filter( $form['fields'], static fn( $f ) => 'acme_social_settings[networks][]' === $f['name'] && $f['checked'] ), 'value' );
		sort( $checked );
		$this->assertSame( array( 'facebook', 'linkedin', 'mastodon', 'twitter' ), $checked );

		$form = $this->get_form( self::screen( 'acme-social-og' ), $this->login( 'admin' ) );
		$by_name = array_column( $form['fields'], 'value', 'name' );
		$this->assertSame( '1234567890', $by_name['acme_social_settings[fb_app_id]'] );
		$this->assertSame( (string) $this->image_id(), $by_name['acme_social_settings[og_default_image]'] );
		$this->assertSame( 'summary_large_image', $by_name['acme_social_settings[twitter_card]'] );
	}

	public function test_saving_the_sharing_screen_only_changes_its_fields(): void {
		$html = $this->save_screen(
			'acme-social',
			array(
				'acme_social_settings[share_enabled]' => false,
				'acme_social_settings[networks][]'    => array( 'email', 'facebook', 'mastodon' ),
				'acme_social_settings[position]'      => 'both',
				'acme_social_settings[post_types][]'  => array( 'page', 'event' ),
				'acme_social_settings[button_style]'  => 'text',
			),
			'admin',
			array( array( 'acme_social_settings[networks][]', 'myspace' ), array( 'acme_social_settings[post_types][]', 'product' ) )
		);
		$this->assertNotice( $html, 'success', 'Settings saved.' );

		$expected = array_merge(
			$this->expected_pristine(),
			array(
				'share_enabled' => false,
				'networks'      => array( 'facebook', 'email', 'mastodon' ),
				'position'      => 'both',
				'post_types'    => array( 'page', 'event' ),
				'button_style'  => 'text',
			)
		);
		$stored = $this->stored();
		sort( $expected['networks'] );
		sort( $stored['networks'] );
		sort( $expected['post_types'] );
		sort( $stored['post_types'] );
		$this->assertSettings( $expected, $stored );
		$this->assertSame( array(), $this->legacy_rows(), 'Saving must not write the old options' );

		// Re-enable and check the front end follows immediately.
		$this->save_screen( 'acme-social', array( 'acme_social_settings[share_enabled]' => true ) );
		$this->assertTrue( $this->stored()['share_enabled'] );
		$this->assertSame( 0, $this->share_blocks( $this->page( '/launch-week/' ) ), 'posts are no longer selected' );
		$about = $this->page( '/about-us/' );
		$this->assertSame( 2, $this->share_blocks( $about ), 'position both' );
		$this->assertStringContainsString( 'acme-social-share--text', $about );
		$networks = $this->share_networks( $about );
		sort( $networks );
		$this->assertSame( array( 'email', 'facebook', 'mastodon' ), $networks );
	}

	public function test_saving_profiles_and_open_graph_screens(): void {
		$html = $this->save_screen(
			'acme-social-profiles',
			array(
				'acme_social_settings[twitter]'   => '@New_Handle',
				'acme_social_settings[facebook]'  => '',
				'acme_social_settings[youtube]'   => 'https://www.youtube.com/@acme',
			)
		);
		$this->assertNotice( $html, 'success', 'Settings saved.' );

		$html = $this->save_screen(
			'acme-social-og',
			array(
				'acme_social_settings[og_enabled]'       => true,
				'acme_social_settings[og_default_image]' => '0',
				'acme_social_settings[fb_app_id]'        => '',
				'acme_social_settings[twitter_card]'     => 'summary',
			)
		);
		$this->assertNotice( $html, 'success', 'Settings saved.' );

		$this->assertSettings(
			array_merge(
				$this->expected_pristine(),
				array(
					'twitter'          => 'New_Handle',
					'facebook'         => '',
					'youtube'          => 'https://www.youtube.com/@acme',
					'og_default_image' => 0,
					'fb_app_id'        => '',
					'twitter_card'     => 'summary',
				)
			),
			$this->stored()
		);

		// Unchecking the only checkbox of the Open Graph screen turns the tags off, nothing else.
		$this->save_screen( 'acme-social-og', array( 'acme_social_settings[og_enabled]' => false ) );
		$stored = $this->stored();
		$this->assertFalse( $stored['og_enabled'] );
		$this->assertTrue( $stored['share_enabled'] );
		$this->assertSame( 'summary', $stored['twitter_card'] );
		$this->assertSame( 'New_Handle', $stored['twitter'] );
	}

	public function test_invalid_values_are_rejected_with_visible_errors(): void {
		$html = $this->save_screen(
			'acme-social-profiles',
			array(
				'acme_social_settings[twitter]'   => 'not a handle!',
				'acme_social_settings[facebook]'  => 'javascript:alert(1)',
				'acme_social_settings[instagram]' => 'https://www.instagram.com/acme_new',
				'acme_social_settings[linkedin]'  => 'not a url',
			)
		);
		$this->assertNotice( $html, 'error', 'Twitter/X username' );
		$this->assertNotice( $html, 'error', 'Facebook page URL' );
		$this->assertNotice( $html, 'error', 'LinkedIn URL' );

		$stored = $this->stored();
		$this->assertSame( 'AcmeHQ', $stored['twitter'], 'invalid values keep the previous value' );
		$this->assertSame( 'https://www.facebook.com/acmehq', $stored['facebook'] );
		$this->assertSame( '', $stored['linkedin'] );
		$this->assertSame( 'https://www.instagram.com/acme_new', $stored['instagram'], 'valid fields of the same submission are saved' );

		$html = $this->save_screen(
			'acme-social-og',
			array(
				'acme_social_settings[fb_app_id]'        => '12ab',
				'acme_social_settings[og_default_image]' => (string) $this->post_id( 'launch-week' ),
				'acme_social_settings[twitter_card]'     => 'summary',
			)
		);
		$this->assertNotice( $html, 'error', 'Facebook App ID' );
		$this->assertNotice( $html, 'error', 'Default share image' );
		$stored = $this->stored();
		$this->assertSame( '1234567890', $stored['fb_app_id'] );
		$this->assertSame( $this->image_id(), $stored['og_default_image'] );
		$this->assertSame( 'summary', $stored['twitter_card'] );

		// Values outside the offered choices are not stored either.
		$login = $this->login( 'admin' );
		$form  = $this->get_form( self::screen( 'acme-social' ), $login );
		$body  = $this->form_body( $form, array(), array( 'acme_social_settings[position]', 'acme_social_settings[button_style]' ), array( array( 'acme_social_settings[position]', 'sideways' ), array( 'acme_social_settings[button_style]', '<b>big</b>' ) ) );
		$this->post_form( $form, $body, $login );
		$stored = $this->stored();
		$this->assertSame( 'after', $stored['position'] );
		$this->assertSame( 'icons_text', $stored['button_style'] );
	}

	public function test_old_option_names_keep_returning_current_settings(): void {
		$read = 'array( get_option( "acme_social_twitter" ), get_option( "acme_social_facebook" ), get_option( "acme_social_networks" ), get_option( "acme_og_enabled" ) )';
		$this->assertSame( array( '@AcmeHQ', 'https://www.facebook.com/acmehq', 'facebook,twitter,linkedin,mastodon', '1' ), $this->cli_json( $read ) );

		$this->save_screen(
			'acme-social-profiles',
			array(
				'acme_social_settings[twitter]'  => '',
				'acme_social_settings[facebook]' => 'https://facebook.com/acme-new',
			)
		);
		$this->save_screen( 'acme-social', array( 'acme_social_settings[networks][]' => array( 'facebook', 'email' ) ) );
		$this->save_screen( 'acme-social-og', array( 'acme_social_settings[og_enabled]' => false ) );

		$this->assertSame( array( '', 'https://facebook.com/acme-new', 'facebook,email', '' ), $this->cli_json( $read ) );

		$this->save_screen( 'acme-social-profiles', array( 'acme_social_settings[twitter]' => 'Back_Again' ) );
		$this->save_screen( 'acme-social-og', array( 'acme_social_settings[og_enabled]' => true ) );
		$this->assertSame( array( '@Back_Again', 'https://facebook.com/acme-new', 'facebook,email', '1' ), $this->cli_json( $read ) );

		$this->assertSame( array(), $this->legacy_rows(), 'reading the old names must not bring the old rows back' );
	}

	public function test_front_end_reflects_open_graph_changes_immediately(): void {
		$before = $this->page( '/launch-week/' );
		$this->assertSame( 'summary_large_image', $this->meta( $before, 'name', 'twitter:card' ) );
		$this->assertSame( '1234567890', $this->meta( $before, 'property', 'fb:app_id' ) );

		$this->save_screen(
			'acme-social-og',
			array(
				'acme_social_settings[fb_app_id]'    => '987654321',
				'acme_social_settings[twitter_card]' => 'summary',
			)
		);
		$this->save_screen( 'acme-social-profiles', array( 'acme_social_settings[twitter]' => 'OtherHandle' ) );

		$after = $this->page( '/launch-week/' );
		$this->assertSame( 'summary', $this->meta( $after, 'name', 'twitter:card' ) );
		$this->assertSame( '987654321', $this->meta( $after, 'property', 'fb:app_id' ) );
		$this->assertSame( '@OtherHandle', $this->meta( $after, 'name', 'twitter:site' ) );

		$this->save_screen( 'acme-social-og', array( 'acme_social_settings[og_enabled]' => false ) );
		$this->assertNull( $this->meta( $this->page( '/launch-week/' ), 'property', 'og:title' ) );
	}
}
