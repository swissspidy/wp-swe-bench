<?php
/**
 * Redirect rule value object.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

defined( 'ABSPATH' ) || exit;

/**
 * A redirect rule.
 *
 * Rows written by 1.x may contain legacy values; from_row() normalizes them:
 * - `match_type` empty (1.0 only knew exact rules)          => 'exact'
 * - `status_code` 0 (1.0 always redirected with a 301)      => 301
 * - `priority` NULL (added in 2.0)                          => 10
 * - `enabled` NULL (added in 1.2, old rows were all active) => enabled
 * - exact/prefix sources without a leading slash (1.0 UI)   => "/" prepended
 */
class Rule {

	const DEFAULT_PRIORITY = 10;

	/**
	 * Rule ID (0 for unsaved rules).
	 *
	 * @var int
	 */
	public $id = 0;

	/**
	 * Source: a path (exact/prefix) or a regular expression without delimiters.
	 *
	 * @var string
	 */
	public $source = '';

	/**
	 * Target: a path on this site or an absolute URL. Empty for 410 rules.
	 *
	 * @var string
	 */
	public $target = '';

	/**
	 * One of exact, prefix, regex.
	 *
	 * @var string
	 */
	public $match_type = 'exact';

	/**
	 * HTTP status code.
	 *
	 * @var int
	 */
	public $status = 301;

	/**
	 * Lower numbers are checked first.
	 *
	 * @var int
	 */
	public $priority = self::DEFAULT_PRIORITY;

	/**
	 * Whether the rule is active.
	 *
	 * @var bool
	 */
	public $enabled = true;

	/**
	 * Hit counter.
	 *
	 * @var int
	 */
	public $hits = 0;

	/**
	 * Last hit (UTC MySQL datetime) or ''.
	 *
	 * @var string
	 */
	public $last_hit = '';

	/**
	 * Free-text note.
	 *
	 * @var string
	 */
	public $note = '';

	/**
	 * Created (UTC MySQL datetime).
	 *
	 * @var string
	 */
	public $created = '';

	/**
	 * Last modified (UTC MySQL datetime).
	 *
	 * @var string
	 */
	public $updated = '';

	/**
	 * Builds a rule from a database row, normalizing legacy values.
	 *
	 * @param object|array $row Row.
	 * @return Rule
	 */
	public static function from_row( $row ) {
		$row  = (array) $row;
		$rule = new self();

		$rule->id         = (int) ( $row['id'] ?? 0 );
		$rule->match_type = ! empty( $row['match_type'] ) ? (string) $row['match_type'] : 'exact';
		$rule->source     = (string) ( $row['source'] ?? '' );
		$rule->target     = (string) ( $row['target'] ?? '' );
		$rule->status     = ! empty( $row['status_code'] ) ? (int) $row['status_code'] : 301;
		$rule->priority   = isset( $row['priority'] ) && '' !== $row['priority'] ? (int) $row['priority'] : self::DEFAULT_PRIORITY;
		$rule->enabled    = ! isset( $row['enabled'] ) || '' === $row['enabled'] ? true : (bool) (int) $row['enabled'];
		$rule->hits       = (int) ( $row['hits'] ?? 0 );
		$rule->last_hit   = self::date_or_empty( $row['last_hit'] ?? '' );
		$rule->note       = (string) ( $row['note'] ?? '' );
		$rule->created    = self::date_or_empty( $row['created_at'] ?? '' );
		$rule->updated    = self::date_or_empty( $row['updated_at'] ?? '' );

		if ( 'regex' !== $rule->match_type && '' !== $rule->source && '/' !== $rule->source[0] ) {
			$rule->source = '/' . $rule->source;
		}

		return $rule;
	}

	/**
	 * Builds a rule from (validated) user input.
	 *
	 * @param array $data Validated data (see Rule_Validator::validate()).
	 * @return Rule
	 */
	public static function from_array( array $data ) {
		$rule = new self();
		foreach ( array( 'id', 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'note' ) as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$rule->$key = $data[ $key ];
			}
		}
		$rule->id       = (int) $rule->id;
		$rule->status   = (int) $rule->status;
		$rule->priority = (int) $rule->priority;
		$rule->enabled  = (bool) $rule->enabled;
		return $rule;
	}

	/**
	 * Database columns for insert/update.
	 *
	 * @return array
	 */
	public function to_row() {
		return array(
			'source'      => $this->source,
			'target'      => $this->target,
			'match_type'  => $this->match_type,
			'status_code' => $this->status,
			'priority'    => $this->priority,
			'enabled'     => $this->enabled ? 1 : 0,
			'note'        => $this->note,
		);
	}

	/**
	 * Public representation (admin screen, export).
	 *
	 * @return array
	 */
	public function to_array() {
		return array(
			'id'         => $this->id,
			'source'     => $this->source,
			'target'     => $this->target,
			'match_type' => $this->match_type,
			'status'     => $this->status,
			'priority'   => $this->priority,
			'enabled'    => $this->enabled,
			'hits'       => $this->hits,
			'last_hit'   => $this->last_hit,
			'note'       => $this->note,
			'created'    => $this->created,
			'updated'    => $this->updated,
		);
	}

	/**
	 * Row for the CSV export (and the CSV format in general).
	 *
	 * @return array<string,string|int>
	 */
	public function to_csv_row() {
		return array(
			'source'     => $this->source,
			'target'     => $this->target,
			'match_type' => $this->match_type,
			'status'     => $this->status,
			'priority'   => $this->priority,
			'enabled'    => $this->enabled ? 'yes' : 'no',
			'note'       => $this->note,
		);
	}

	/**
	 * Column names of the CSV format.
	 *
	 * @return string[]
	 */
	public static function csv_columns() {
		return array( 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'note' );
	}

	/**
	 * Whether this rule sends visitors somewhere (as opposed to 410 Gone).
	 *
	 * @return bool
	 */
	public function is_redirect() {
		return 410 !== $this->status;
	}

	/**
	 * Normalizes an empty/zero date to ''.
	 *
	 * @param string|null $date Date.
	 * @return string
	 */
	private static function date_or_empty( $date ) {
		return ( empty( $date ) || '0000-00-00 00:00:00' === $date ) ? '' : (string) $date;
	}
}
