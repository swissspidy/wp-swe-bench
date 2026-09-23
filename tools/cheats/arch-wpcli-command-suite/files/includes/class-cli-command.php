<?php
/**
 * WP-CLI commands: `wp acme-redirects …`.
 *
 * @package Acme\Redirects
 */

namespace Acme\Redirects;

use WP_CLI;
use WP_CLI\Formatter;

defined( 'ABSPATH' ) || exit;

/**
 * Manage redirect rules.
 *
 * ## EXAMPLES
 *
 *     wp acme-redirects list --match_type=regex --format=csv
 *     wp acme-redirects add /old-page/ /new-page/ --status=301
 *     wp acme-redirects import redirects.csv --dry-run --report=report.csv
 *     wp acme-redirects test https://example.com/old-page/?utm_source=x
 */
class CLI_Command {

	const DEFAULT_FIELDS = array( 'id', 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'hits' );

	const ALL_FIELDS = array( 'id', 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'hits', 'last_hit', 'note', 'created', 'updated' );

	/**
	 * Repository.
	 *
	 * @var Rule_Repository
	 */
	private $rules;

	/**
	 * Matcher.
	 *
	 * @var Matcher
	 */
	private $matcher;

	/**
	 * Constructor.
	 *
	 * @param Rule_Repository $rules   Repository.
	 * @param Matcher         $matcher Matcher.
	 */
	public function __construct( Rule_Repository $rules, Matcher $matcher ) {
		$this->rules   = $rules;
		$this->matcher = $matcher;
	}

	/**
	 * Lists redirect rules in matching order (priority, then ID).
	 *
	 * ## OPTIONS
	 *
	 * [--match_type=<type>]
	 * : Only rules of this match type.
	 * ---
	 * options:
	 *   - exact
	 *   - prefix
	 *   - regex
	 * ---
	 *
	 * [--status=<code>]
	 * : Only rules with this status code.
	 *
	 * [--enabled=<yes|no>]
	 * : Only enabled (yes) or disabled (no) rules.
	 *
	 * [--search=<text>]
	 * : Only rules whose source or target contains the text.
	 *
	 * [--field=<field>]
	 * : Print the value of a single field for each rule.
	 *
	 * [--fields=<fields>]
	 * : Comma-separated list of fields. Default: id,source,target,match_type,status,priority,enabled,hits. Also available: last_hit, note, created, updated.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - ids
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp acme-redirects list --match_type=regex --fields=id,source,hits --format=csv
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function list_( $args, $assoc_args ) {
		$query = $this->filters( $assoc_args );
		$items = array();
		foreach ( $this->rules->each( $query ) as $rule ) {
			$items[] = $this->item( $rule );
		}

		$format = $assoc_args['format'] ?? 'table';
		if ( 'ids' === $format ) {
			$items = wp_list_pluck( $items, 'id' );
		}

		if ( isset( $assoc_args['fields'] ) ) {
			$fields = array_map( 'trim', explode( ',', $assoc_args['fields'] ) );
			$bad    = array_diff( $fields, self::ALL_FIELDS );
			if ( $bad ) {
				WP_CLI::error( sprintf( 'Invalid field(s): %s. Available fields: %s.', implode( ', ', $bad ), implode( ', ', self::ALL_FIELDS ) ) );
			}
		}
		if ( isset( $assoc_args['field'] ) && ! in_array( $assoc_args['field'], self::ALL_FIELDS, true ) ) {
			WP_CLI::error( sprintf( 'Invalid field: %s. Available fields: %s.', $assoc_args['field'], implode( ', ', self::ALL_FIELDS ) ) );
		}

		$formatter = new Formatter( $assoc_args, self::DEFAULT_FIELDS );
		$formatter->display_items( $items );
	}

	/**
	 * Adds a redirect rule.
	 *
	 * ## OPTIONS
	 *
	 * <source>
	 * : Path (exact/prefix) or regular expression (regex) to match.
	 *
	 * [<target>]
	 * : Path on this site or full URL. Not used for 410 rules.
	 *
	 * [--match_type=<type>]
	 * : exact, prefix or regex.
	 * ---
	 * default: exact
	 * ---
	 *
	 * [--status=<code>]
	 * : 301, 302, 307, 308 or 410.
	 * ---
	 * default: 301
	 * ---
	 *
	 * [--priority=<number>]
	 * : 0-100, lower numbers are checked first.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--note=<note>]
	 * : Note.
	 *
	 * [--disabled]
	 * : Create the rule disabled.
	 *
	 * [--porcelain]
	 * : Only print the new rule's ID.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function add( $args, $assoc_args ) {
		$input = array(
			'source'     => $args[0],
			'target'     => $args[1] ?? '',
			'match_type' => $assoc_args['match_type'] ?? 'exact',
			'status'     => $assoc_args['status'] ?? 301,
			'priority'   => $assoc_args['priority'] ?? Rule::DEFAULT_PRIORITY,
			'enabled'    => ! WP_CLI\Utils\get_flag_value( $assoc_args, 'disabled', false ),
			'note'       => $assoc_args['note'] ?? '',
		);

		$data = Plugin::instance()->validator()->validate( $input );
		if ( is_wp_error( $data ) ) {
			WP_CLI::error( implode( ' ', $data->get_error_messages() ) );
		}

		$id = Bulk_Writer::insert( Rule::from_array( $data ) );
		if ( is_wp_error( $id ) ) {
			WP_CLI::error( $id->get_error_message() );
		}

		if ( WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain', false ) ) {
			WP_CLI::line( (string) $id );
			return;
		}
		WP_CLI::success( sprintf( 'Created redirect %d.', $id ) );
	}

	/**
	 * Updates a redirect rule. Fields that are not given keep their values; hits are kept.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Rule ID.
	 *
	 * [--source=<source>]
	 * : New source.
	 *
	 * [--target=<target>]
	 * : New target.
	 *
	 * [--match_type=<type>]
	 * : New match type.
	 *
	 * [--status=<code>]
	 * : New status code.
	 *
	 * [--priority=<number>]
	 * : New priority.
	 *
	 * [--enabled=<yes|no>]
	 * : Enable or disable the rule.
	 *
	 * [--note=<note>]
	 * : New note.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function update( $args, $assoc_args ) {
		$id   = $this->id_arg( $args[0] );
		$rule = $id ? $this->rules->find( $id ) : null;
		if ( ! $rule ) {
			WP_CLI::error( sprintf( 'Redirect %s not found.', $args[0] ) );
		}

		$input = $rule->to_array();
		$known = array( 'source', 'target', 'match_type', 'status', 'priority', 'enabled', 'note' );
		$given = array_intersect_key( $assoc_args, array_flip( $known ) );
		if ( ! $given ) {
			WP_CLI::error( 'Nothing to update. Pass at least one of --' . implode( ', --', $known ) . '.' );
		}
		if ( isset( $given['enabled'] ) && ! in_array( strtolower( (string) $given['enabled'] ), array( 'yes', 'no' ), true ) ) {
			WP_CLI::error( '--enabled must be yes or no.' );
		}
		$input = array_merge( $input, $given );

		$data = Plugin::instance()->validator()->validate( $input, array( 'id' => $rule->id ) );
		if ( is_wp_error( $data ) ) {
			WP_CLI::error( implode( ' ', $data->get_error_messages() ) );
		}

		$result = Bulk_Writer::update( Rule::from_array( $data ) );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}
		WP_CLI::success( sprintf( 'Updated redirect %d.', $rule->id ) );
	}

	/**
	 * Deletes one or more redirect rules.
	 *
	 * ## OPTIONS
	 *
	 * <id>...
	 * : Rule IDs.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function delete( $args, $assoc_args ) {
		$deleted = 0;
		$missing = 0;
		foreach ( $args as $arg ) {
			$id = $this->id_arg( $arg );
			if ( $id && Bulk_Writer::delete( $id ) ) {
				++$deleted;
			} else {
				++$missing;
				WP_CLI::warning( sprintf( 'Redirect %s not found.', $arg ) );
			}
		}
		if ( $missing ) {
			WP_CLI::error( sprintf( 'Deleted %d redirect(s), %d not found.', $deleted, $missing ) );
		}
		WP_CLI::success( sprintf( 'Deleted %d redirect(s).', $deleted ) );
	}

	/**
	 * Imports rules from a CSV file (the format of `export`).
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : CSV file with a header row.
	 *
	 * [--update]
	 * : Update rules whose source already exists (default: skip them).
	 *
	 * [--dry-run]
	 * : Validate and report without changing anything.
	 *
	 * [--report=<file>]
	 * : Write a CSV report (row,source,result,message) to this file.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function import( $args, $assoc_args ) {
		$dry_run = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run', false );
		$update  = (bool) WP_CLI\Utils\get_flag_value( $assoc_args, 'update', false );
		$report  = null;

		if ( ! is_file( $args[0] ) || ! is_readable( $args[0] ) ) {
			WP_CLI::error( sprintf( 'Cannot read %s.', $args[0] ) );
		}

		if ( ! empty( $assoc_args['report'] ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
			$report = fopen( $assoc_args['report'], 'w' );
			if ( ! $report ) {
				WP_CLI::error( sprintf( 'Cannot write the report to %s.', $assoc_args['report'] ) );
			}
			fputcsv( $report, array( 'row', 'source', 'result', 'message' ), ',', '"', '' );
		}

		$importer = new Csv_Importer( $this->rules, Plugin::instance()->validator() );
		$counts   = $importer->import(
			$args[0],
			array(
				'dry_run' => $dry_run,
				'update'  => $update,
			),
			static function ( $row, $source, $result, $message ) use ( $report ) {
				if ( Csv_Importer::INVALID === $result ) {
					WP_CLI::warning( sprintf( 'Row %d: %s', $row, $message ) );
				}
				if ( $report ) {
					fputcsv( $report, array( $row, $source, $result, $message ), ',', '"', '' );
				}
			}
		);

		if ( $report ) {
			fclose( $report ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		}
		if ( is_wp_error( $counts ) ) {
			WP_CLI::error( $counts->get_error_message() );
		}

		$summary = $dry_run
			? sprintf( 'Dry run: %d would be created, %d would be updated, %d skipped, %d invalid.', $counts['created'], $counts['updated'], $counts['skipped'], $counts['invalid'] )
			: sprintf( '%d created, %d updated, %d skipped, %d invalid.', $counts['created'], $counts['updated'], $counts['skipped'], $counts['invalid'] );

		if ( $counts['invalid'] > 0 ) {
			WP_CLI::error( $summary );
		}
		WP_CLI::success( $summary );
	}

	/**
	 * Exports rules as CSV (same format as Tools → Redirects → Export CSV).
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Write to this file instead of STDOUT.
	 *
	 * [--match_type=<type>]
	 * : Only rules of this match type.
	 *
	 * [--status=<code>]
	 * : Only rules with this status code.
	 *
	 * [--enabled=<yes|no>]
	 * : Only enabled or disabled rules.
	 *
	 * [--search=<text>]
	 * : Only rules whose source or target contains the text.
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function export( $args, $assoc_args ) {
		$query = $this->filters( $assoc_args );
		$file  = $args[0] ?? '';
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = '' !== $file ? fopen( $file, 'w' ) : fopen( 'php://stdout', 'w' );
		if ( ! $out ) {
			WP_CLI::error( sprintf( 'Cannot write to %s.', $file ) );
		}

		fputcsv( $out, Rule::csv_columns(), ',', '"', '' );
		$count = 0;
		foreach ( $this->rules->each( $query ) as $rule ) {
			fputcsv( $out, array_values( $rule->to_csv_row() ), ',', '"', '' );
			++$count;
		}
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( '' !== $file ) {
			WP_CLI::success( sprintf( 'Exported %d redirect(s) to %s.', $count, $file ) );
		}
	}

	/**
	 * Shows which rule a URL matches and where it would redirect to. Does not count a hit.
	 *
	 * ## OPTIONS
	 *
	 * <url>
	 * : A URL on this site or a path, with an optional query string.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 * ---
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Options.
	 */
	public function test( $args, $assoc_args ) {
		$format = $assoc_args['format'] ?? 'table';
		$result = $this->matcher->match( $args[0] );

		if ( ! $result ) {
			if ( 'json' === $format ) {
				WP_CLI::line( (string) wp_json_encode( array( 'matched' => false ) ) );
				WP_CLI::halt( 1 );
			}
			WP_CLI::error( sprintf( 'No redirect matches %s.', $args[0] ) );
		}

		$rule   = $result['rule'];
		$target = '' !== $result['target'] ? wp_sanitize_redirect( $result['target'] ) : '';
		$data   = array(
			'matched'    => true,
			'id'         => $rule->id,
			'source'     => $rule->source,
			'match_type' => $rule->match_type,
			'priority'   => $rule->priority,
			'status'     => $result['status'],
			'target'     => $target,
		);

		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $data, JSON_UNESCAPED_SLASHES ) );
			return;
		}
		unset( $data['matched'] );
		$formatter = new Formatter( $assoc_args, array_keys( $data ) );
		$formatter->display_items( array( $data ) );
	}

	/**
	 * Repository query args from the list/export filters.
	 *
	 * @param array $assoc_args Options.
	 * @return array
	 */
	private function filters( array $assoc_args ) {
		$query = array( 'orderby' => 'priority' );
		if ( isset( $assoc_args['match_type'] ) ) {
			if ( ! array_key_exists( $assoc_args['match_type'], match_types() ) ) {
				WP_CLI::error( sprintf( 'Unknown match type "%s".', $assoc_args['match_type'] ) );
			}
			$query['match_type'] = $assoc_args['match_type'];
		}
		if ( isset( $assoc_args['status'] ) ) {
			if ( ! array_key_exists( (int) $assoc_args['status'], status_codes() ) ) {
				WP_CLI::error( sprintf( 'Unsupported status code "%s".', $assoc_args['status'] ) );
			}
			$query['status'] = (int) $assoc_args['status'];
		}
		if ( isset( $assoc_args['enabled'] ) ) {
			$enabled = strtolower( (string) $assoc_args['enabled'] );
			if ( ! in_array( $enabled, array( 'yes', 'no' ), true ) ) {
				WP_CLI::error( '--enabled must be yes or no.' );
			}
			$query['enabled'] = $enabled;
		}
		if ( isset( $assoc_args['search'] ) ) {
			$query['search'] = (string) $assoc_args['search'];
		}
		return $query;
	}

	/**
	 * Output row for a rule.
	 *
	 * @param Rule $rule Rule.
	 * @return array
	 */
	private function item( Rule $rule ) {
		$item            = $rule->to_array();
		$item['enabled'] = $rule->enabled ? 'yes' : 'no';
		return $item;
	}

	/**
	 * Parses a rule ID argument.
	 *
	 * @param string $arg Argument.
	 * @return int 0 if not a positive integer.
	 */
	private function id_arg( $arg ) {
		return ctype_digit( (string) $arg ) ? (int) $arg : 0;
	}
}
