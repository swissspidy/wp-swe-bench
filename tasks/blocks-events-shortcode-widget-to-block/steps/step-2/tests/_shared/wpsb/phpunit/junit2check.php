<?php
/**
 * Convert a PHPUnit JUnit XML log into a wp-swe-bench check JSON.
 * Usage: php junit2check.php <name> <required 0|1> <junit.xml> <stdout.log>
 */
[ , $name, $required, $junit, $log ] = $argv;
$result = array(
	'name'     => $name,
	'required' => (bool) $required,
	'passed'   => 0,
	'total'    => 0,
	'ok'       => false,
	'failures' => array(),
);
if ( ! is_file( $junit ) || ! ( $xml = @simplexml_load_file( $junit ) ) ) {
	$tail                 = is_file( $log ) ? substr( file_get_contents( $log ), -3000 ) : '';
	$result['total']      = 1;
	$result['failures'][] = "PHPUnit did not produce a JUnit report (fatal error while loading?):\n" . $tail;
	echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
	exit( 0 );
}
foreach ( $xml->xpath( '//testcase' ) as $case ) {
	++$result['total'];
	$id = (string) $case['class'] . '::' . (string) $case['name'];
	$bad = null;
	foreach ( array( 'failure', 'error' ) as $kind ) {
		if ( isset( $case->{$kind} ) ) {
			$bad = $kind . ': ' . substr( trim( (string) $case->{$kind} ), 0, 1200 );
		}
	}
	if ( null === $bad && isset( $case->skipped ) ) {
		$bad = 'skipped (skipped/incomplete tests count as failures)';
	}
	if ( null === $bad ) {
		++$result['passed'];
	} else {
		$result['failures'][] = $id . ' => ' . $bad;
	}
}
if ( 0 === $result['total'] ) {
	$result['total']      = 1;
	$result['failures'][] = 'No tests were executed.';
}
$result['ok'] = $result['passed'] === $result['total'];
echo json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ), "\n";
