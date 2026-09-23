<?php
/**
 * Admin page template.
 *
 * @package Acme\Migrate
 *
 * @var array|false $last    Last run notice.
 * @var string[]    $tables  Known tables.
 * @var array[]     $history Recent runs.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap acme-migrate">
	<h1><?php esc_html_e( 'Acme Migrate', 'acme-migrate' ); ?></h1>

	<?php if ( is_array( $last ) && isset( $last['error'] ) ) : ?>
		<div class="notice notice-error"><p><?php echo esc_html( $last['error'] ); ?></p></div>
	<?php elseif ( is_array( $last ) ) : ?>
		<?php $last_report = \Acme\Migrate\Report::from_array( $last['report'] ); ?>
		<div class="notice notice-success acme-migrate-result">
			<p class="acme-migrate-summary"><?php echo esc_html( $last['summary'] ); ?></p>
			<?php if ( $last_report->get_items() ) : ?>
				<table class="widefat striped acme-migrate-report">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Table', 'acme-migrate' ); ?></th>
							<th><?php esc_html_e( 'Column', 'acme-migrate' ); ?></th>
							<th><?php esc_html_e( 'Rows', 'acme-migrate' ); ?></th>
							<th><?php esc_html_e( 'Replacements', 'acme-migrate' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $last_report->get_items() as $item ) : ?>
							<tr>
								<td><?php echo esc_html( $item['table'] ); ?></td>
								<td><?php echo esc_html( $item['column'] ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $item['rows'] ) ); ?></td>
								<td><?php echo esc_html( number_format_i18n( $item['replacements'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php foreach ( $last_report->get_warnings() as $warning ) : ?>
				<p class="acme-migrate-warning"><?php echo esc_html( $warning ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<p><?php esc_html_e( 'Replace a string everywhere in the database, for example the old site URL after moving the site. Serialized settings are updated safely.', 'acme-migrate' ); ?></p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acme-migrate-form">
		<input type="hidden" name="action" value="<?php echo esc_attr( \Acme\Migrate\Admin_Page::ACTION ); ?>" />
		<?php wp_nonce_field( \Acme\Migrate\Admin_Page::ACTION ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="acme-migrate-search"><?php esc_html_e( 'Search for', 'acme-migrate' ); ?></label></th>
				<td><input type="text" class="regular-text code" id="acme-migrate-search" name="acme_migrate[search]" placeholder="http://old.example.com" required /></td>
			</tr>
			<tr>
				<th scope="row"><label for="acme-migrate-replace"><?php esc_html_e( 'Replace with', 'acme-migrate' ); ?></label></th>
				<td><input type="text" class="regular-text code" id="acme-migrate-replace" name="acme_migrate[replace]" placeholder="https://www.example.com" /></td>
			</tr>
			<tr>
				<th scope="row"><label for="acme-migrate-tables"><?php esc_html_e( 'Tables', 'acme-migrate' ); ?></label></th>
				<td>
					<select id="acme-migrate-tables" name="acme_migrate[tables][]" multiple size="8">
						<?php foreach ( $tables as $table_name ) : ?>
							<option value="<?php echo esc_attr( $table_name ); ?>"><?php echo esc_html( $table_name ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'Leave empty to search all tables.', 'acme-migrate' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Options', 'acme-migrate' ); ?></th>
				<td>
					<label><input type="checkbox" name="acme_migrate[dry_run]" value="1" checked /> <?php esc_html_e( 'Dry run (only report what would change)', 'acme-migrate' ); ?></label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Run search & replace', 'acme-migrate' ) ); ?>
	</form>

	<h2><?php esc_html_e( 'Recent runs', 'acme-migrate' ); ?></h2>
	<?php if ( ! $history ) : ?>
		<p><?php esc_html_e( 'No runs yet.', 'acme-migrate' ); ?></p>
	<?php else : ?>
		<table class="widefat striped acme-migrate-history">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'acme-migrate' ); ?></th>
					<th><?php esc_html_e( 'Search', 'acme-migrate' ); ?></th>
					<th><?php esc_html_e( 'Replace', 'acme-migrate' ); ?></th>
					<th><?php esc_html_e( 'Dry run', 'acme-migrate' ); ?></th>
					<th><?php esc_html_e( 'Rows', 'acme-migrate' ); ?></th>
					<th><?php esc_html_e( 'Replacements', 'acme-migrate' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $history as $run ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $run['time'] ) ); ?></td>
						<td><code><?php echo esc_html( $run['search'] ); ?></code></td>
						<td><code><?php echo esc_html( $run['replace'] ); ?></code></td>
						<td><?php echo $run['dry_run'] ? esc_html__( 'Yes', 'acme-migrate' ) : esc_html__( 'No', 'acme-migrate' ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $run['rows'] ) ); ?></td>
						<td><?php echo esc_html( number_format_i18n( (int) $run['replacements'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
