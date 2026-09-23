<?php
/**
 * Member profile page.
 *
 * @package Acme\Members
 *
 * @var \WP_User $member Member.
 * @var array    $fields Field definitions.
 * @var array    $values Visible, non-empty field values.
 */

defined( 'ABSPATH' ) || exit;
?>
<main id="primary" class="acme-member-profile" data-member-id="<?php echo esc_attr( (string) $member->ID ); ?>">
	<header class="acme-member-profile__header">
		<?php echo get_avatar( $member->ID, 96 ); ?>
		<h1 class="acme-member-profile__name"><?php echo esc_html( $member->display_name ); ?></h1>
	</header>
	<dl class="acme-member-profile__fields">
		<?php foreach ( $values as $acme_key => $acme_value ) : ?>
			<div class="acme-member-field acme-member-field--<?php echo esc_attr( $acme_key ); ?>">
				<dt><?php echo esc_html( $fields[ $acme_key ]['label'] ); ?></dt>
				<dd>
					<?php if ( 'website' === $acme_key ) : ?>
						<a href="<?php echo esc_url( $acme_value ); ?>" rel="nofollow ugc"><?php echo esc_html( $acme_value ); ?></a>
					<?php elseif ( 'bio' === $acme_key ) : ?>
						<?php echo wp_kses_post( wpautop( esc_html( $acme_value ) ) ); ?>
					<?php else : ?>
						<?php echo esc_html( $acme_value ); ?>
					<?php endif; ?>
				</dd>
			</div>
		<?php endforeach; ?>
	</dl>
</main>
