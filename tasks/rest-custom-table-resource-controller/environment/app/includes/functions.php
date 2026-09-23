<?php
/**
 * Helpers.
 *
 * @package Acme\Leads
 */

defined( 'ABSPATH' ) || exit;

/**
 * Initial lead score for form submissions (0-100).
 *
 * Business addresses and a company name score higher; free mail providers lower.
 *
 * @param string $email   Email.
 * @param string $company Company.
 * @return int
 */
function acme_leads_initial_score( $email, $company ) {
	$score  = 20;
	$domain = strtolower( (string) substr( strrchr( (string) $email, '@' ), 1 ) );
	$free   = array( 'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'gmx.net', 'web.de' );
	if ( $domain && ! in_array( $domain, $free, true ) ) {
		$score += 30;
	}
	if ( '' !== trim( (string) $company ) ) {
		$score += 20;
	}

	/**
	 * Filters the initial score of a lead from the website form.
	 *
	 * @since 1.6.0
	 *
	 * @param int    $score   Score.
	 * @param string $email   Email.
	 * @param string $company Company.
	 */
	return max( 0, min( 100, (int) apply_filters( 'acme_leads_initial_score', $score, $email, $company ) ) );
}

/**
 * Human-readable status labels.
 *
 * @return array<string, string>
 */
function acme_leads_status_labels() {
	return array(
		'new'       => __( 'New', 'acme-leads' ),
		'contacted' => __( 'Contacted', 'acme-leads' ),
		'qualified' => __( 'Qualified', 'acme-leads' ),
		'won'       => __( 'Won', 'acme-leads' ),
		'lost'      => __( 'Lost', 'acme-leads' ),
	);
}
