<?php
/**
 * Tables saved by 1.x that nobody re-saved: front end + structured data must keep working.
 */

use function WPSB\Pricing\is_marked;
use function WPSB\Pricing\json_ld;
use function WPSB\Pricing\render_slug;
use function WPSB\Pricing\tables;

class LegacyTablesTest extends WPSB\TestCase {

	private function assertPlans( array $table, array $expected ): void {
		$this->assertFalse( $table['nested'], 'Old tables must not be wrapped in another table element' );
		$this->assertCount( count( $expected ), $table['plans'] );
		foreach ( $expected as $i => $exp ) {
			$this->assertSame( $exp[0], $table['plans'][ $i ]['name'] );
			$this->assertSame( $exp[1], $table['plans'][ $i ]['amount'] );
			$this->assertSame( $exp[2], is_marked( $table['plans'][ $i ] ), 'Highlighted plan of ' . $exp[0] );
		}
	}

	public function test_hosting_plans_render_as_before(): void {
		$html   = render_slug( 'hosting-plans' );
		$tables = tables( $html );
		$this->assertCount( 1, $tables, $html );
		$this->assertContains( 'acme-pricing--cols-3', $tables[0]['classes'] );
		$this->assertContains( 'acme-pricing--currency-usd', $tables[0]['classes'] );
		$this->assertNotContains( 'acme-pricing--cols-0', $tables[0]['classes'] );
		$this->assertPlans(
			$tables[0],
			array(
				array( 'Starter', '$9', false ),
				array( 'Business', '$29.50', true ),
				array( 'Agency Plus', '$1,200', false ),
			)
		);
		$this->assertSame( array( '1 website', '10 GB storage', 'Email support' ), $tables[0]['plans'][0]['features'] );
		$this->assertStringContainsString( 'Pick the hosting plan', $html );
		$this->assertStringContainsString( 'Talk to us', $html );
	}

	public function test_table_in_group_renders_as_before(): void {
		$tables = tables( render_slug( 'swiss-offers' ) );
		$this->assertCount( 1, $tables );
		$this->assertContains( 'acme-pricing--cols-2', $tables[0]['classes'] );
		$this->assertPlans(
			$tables[0],
			array(
				array( 'Verein', 'CHF 49.–', true ),
				array( 'Gemeinde', "CHF 1'290.50", false ),
			)
		);
	}

	public function test_hidden_plan_stays_hidden_and_two_tables_render(): void {
		$html   = render_slug( 'two-tables' );
		$tables = tables( $html );
		$this->assertCount( 2, $tables, $html );
		$this->assertContains( 'alignwide', $tables[0]['classes'] );
		$this->assertPlans(
			$tables[0],
			array(
				array( 'Basis', '9,90 €', false ),
				array( 'Team', '19 €', false ),
				array( 'Konzern', '2.500 €', false ),
			)
		);
		$this->assertPlans( $tables[1], array( array( 'Annual pass', '£99', true ) ) );
		$this->assertStringNotContainsString( 'Retired plan', $html );
	}

	public function test_table_from_1_0_renders_as_before(): void {
		$tables = tables( render_slug( 'legacy-v1-table', 'post' ) );
		$this->assertCount( 1, $tables );
		$this->assertPlans(
			$tables[0],
			array(
				array( 'Hobby', '$0', false ),
				array( 'Pro', '$19', false ),
				array( 'Team & Co', '$49', true ),
			)
		);
	}

	public function test_no_php_warnings_while_rendering(): void {
		$errors = array();
		set_error_handler(
			static function ( $no, $str, $file, $line ) use ( &$errors ) {
				if ( false !== strpos( $file, 'acme-pricing' ) ) {
					$errors[] = "$str in $file:$line";
				}
				return false;
			}
		);
		try {
			foreach ( array( 'hosting-plans', 'swiss-offers', 'two-tables' ) as $slug ) {
				render_slug( $slug );
			}
			render_slug( 'legacy-v1-table', 'post' );
		} finally {
			restore_error_handler();
		}
		$this->assertSame( array(), $errors );
	}

	public function test_template_tags_and_shortcode(): void {
		$this->assertSame( '$29.50', acme_pricing_format_price( '29.5', 'USD' ) );
		$this->assertSame( '1.290,50 €', acme_pricing_format_price( '1290.5', 'EUR' ) );
		$this->assertSame( "CHF 2'500.–", acme_pricing_format_price( 2500, 'CHF' ) );
		$this->assertSame( '£99', acme_pricing_format_price( '99', 'GBP' ) );
		$this->assertTrue( acme_pricing_has_table( get_page_by_path( 'two-tables' ) ) );
		$this->assertFalse( acme_pricing_has_table( get_page_by_path( 'price-shortcode', OBJECT, 'post' ) ) );

		$html = render_slug( 'price-shortcode', 'post' );
		$this->assertStringContainsString( '<span class="acme-price">$9</span>', $html );
		$this->assertStringContainsString( '<span class="acme-price">CHF 1&#039;290.50</span>', $html );
	}

	public function test_currencies_filter_still_applies(): void {
		$filter = static function ( $currencies ) {
			$currencies['SEK'] = array(
				'label'     => 'Swedish krona',
				'symbol'    => 'kr',
				'position'  => 'after',
				'space'     => true,
				'decimal'   => ',',
				'thousands' => ' ',
				'whole'     => '',
			);
			return $currencies;
		};
		add_filter( 'acme_pricing_currencies', $filter );
		try {
			$this->assertSame( '1 290,50 kr', acme_pricing_format_price( '1290.5', 'SEK' ) );
		} finally {
			remove_filter( 'acme_pricing_currencies', $filter );
		}
	}
}
