<?php
/**
 * Account page template.
 *
 * @package Acme\Loyalty
 *
 * @var array    $profile  Members::get_profile().
 * @var object[] $history  Ledger rows.
 * @var array    $settings Plugin settings.
 * @var int      $user_id  Current user.
 */

defined( 'ABSPATH' ) || exit;

$acme_reasons = acme_loyalty_reasons();
?>
<div class="acme-loyalty-account">
	<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<?php if ( isset( $_GET['acme_loyalty'] ) && 'saved' === $_GET['acme_loyalty'] ) : ?>
		<p class="acme-loyalty-account__notice"><?php esc_html_e( 'Your preferences were saved.', 'acme-loyalty' ); ?></p>
	<?php endif; ?>

	<p class="acme-loyalty-account__balance">
		<?php
		/* translators: %s: formatted points, e.g. "1,250 points" */
		printf( esc_html__( 'Your balance: %s', 'acme-loyalty' ), '<strong>' . esc_html( acme_loyalty_format_points( \Acme\Loyalty\Ledger::balance( $user_id ) ) ) . '</strong>' );
		?>
	</p>
	<p class="acme-loyalty-account__tier">
		<?php
		/* translators: %s: tier name */
		printf( esc_html__( 'Tier: %s', 'acme-loyalty' ), esc_html( acme_loyalty_tier_label( $profile['tier'] ) ) );
		?>
	</p>

	<h3><?php esc_html_e( 'Points history', 'acme-loyalty' ); ?></h3>
	<table class="acme-loyalty-history">
		<thead><tr><th><?php esc_html_e( 'Date', 'acme-loyalty' ); ?></th><th><?php esc_html_e( 'Points', 'acme-loyalty' ); ?></th><th><?php esc_html_e( 'Reason', 'acme-loyalty' ); ?></th></tr></thead>
		<tbody>
		<?php foreach ( $history as $acme_row ) : ?>
			<tr>
				<td><?php echo esc_html( acme_loyalty_format_date( $acme_row->created_at ) ); ?></td>
				<td><?php echo esc_html( number_format_i18n( (int) $acme_row->points ) ); ?></td>
				<td><?php echo esc_html( $acme_reasons[ $acme_row->reason ] ?? $acme_row->reason ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<h3><?php esc_html_e( 'Preferences', 'acme-loyalty' ); ?></h3>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="acme-loyalty-preferences">
		<input type="hidden" name="action" value="acme_loyalty_save_preferences" />
		<?php wp_nonce_field( 'acme_loyalty_preferences', 'acme_loyalty_nonce' ); ?>
		<p>
			<label for="acme-birthday"><?php esc_html_e( 'Birthday', 'acme-loyalty' ); ?></label>
			<input id="acme-birthday" type="date" name="birthday" value="<?php echo esc_attr( $profile['birthday'] ); ?>" />
		</p>
		<p>
			<label for="acme-phone"><?php esc_html_e( 'Phone', 'acme-loyalty' ); ?></label>
			<input id="acme-phone" type="tel" name="phone" value="<?php echo esc_attr( $profile['phone'] ); ?>" />
		</p>
		<fieldset>
			<legend><?php esc_html_e( 'Contact me by', 'acme-loyalty' ); ?></legend>
			<?php foreach ( acme_loyalty_channels() as $acme_slug => $acme_label ) : ?>
				<label><input type="checkbox" name="channels[]" value="<?php echo esc_attr( $acme_slug ); ?>" <?php checked( in_array( $acme_slug, $profile['channels'], true ) ); ?> /> <?php echo esc_html( $acme_label ); ?></label>
			<?php endforeach; ?>
		</fieldset>
		<p>
			<label for="acme-store"><?php esc_html_e( 'Favourite store', 'acme-loyalty' ); ?></label>
			<select id="acme-store" name="store">
				<option value=""><?php esc_html_e( '— None —', 'acme-loyalty' ); ?></option>
				<?php foreach ( (array) $settings['store_names'] as $acme_store ) : ?>
					<option value="<?php echo esc_attr( $acme_store ); ?>" <?php selected( $profile['store'], $acme_store ); ?>><?php echo esc_html( $acme_store ); ?></option>
				<?php endforeach; ?>
			</select>
		</p>
		<p><button type="submit"><?php esc_html_e( 'Save preferences', 'acme-loyalty' ); ?></button></p>
	</form>
</div>
