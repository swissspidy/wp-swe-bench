<?php
/**
 * Specs table.
 *
 * Themes style this markup (catalog.acme.example scrapes it too), keep it stable.
 *
 * @package Acme\Specs
 *
 * @var int   $post_id Product ID.
 * @var array $specs   Specs (see Acme\Specs\Specs::get()).
 * @var array $codes   Certification code => label.
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="acme-specs" data-product="<?php echo (int) $post_id; ?>">
	<caption><?php esc_html_e( 'Specifications', 'acme-specs' ); ?></caption>
	<tbody>
		<?php if ( $specs['dimensions'] ) : ?>
			<tr class="acme-specs__row acme-specs__row--dimensions">
				<th scope="row"><?php esc_html_e( 'Dimensions (W × H × D)', 'acme-specs' ); ?></th>
				<td><?php echo esc_html( acme_specs_format_dimensions( $specs['dimensions'] ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( $specs['materials'] ) : ?>
			<tr class="acme-specs__row acme-specs__row--materials">
				<th scope="row"><?php esc_html_e( 'Materials', 'acme-specs' ); ?></th>
				<td><?php echo esc_html( implode( ', ', $specs['materials'] ) ); ?></td>
			</tr>
		<?php endif; ?>
		<?php if ( $specs['certifications'] ) : ?>
			<tr class="acme-specs__row acme-specs__row--certifications">
				<th scope="row"><?php esc_html_e( 'Certifications', 'acme-specs' ); ?></th>
				<td>
					<ul class="acme-specs__certs">
						<?php foreach ( $specs['certifications'] as $cert ) : ?>
							<li class="acme-specs__cert" data-code="<?php echo esc_attr( $cert['code'] ); ?>">
								<span class="acme-specs__cert-name"><?php echo esc_html( $codes[ $cert['code'] ] ?? $cert['code'] ); ?></span>
								<span class="acme-specs__cert-dates"><?php echo esc_html( acme_specs_format_cert_dates( $cert ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endif; ?>
	</tbody>
</table>
