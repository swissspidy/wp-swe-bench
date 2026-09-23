<?php
/**
 * Directory listing.
 *
 * @package Acme\Members
 *
 * @var array $cards  List of array( 'user' => WP_User, 'values' => visible card field values ).
 * @var int   $total  Number of members listed (all pages).
 * @var int   $pages  Number of pages.
 * @var int   $page   Current page.
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="acme-member-directory">
	<p class="acme-member-directory__count">
		<?php
		/* translators: %s: number of members */
		echo esc_html( sprintf( _n( '%s member', '%s members', $total, 'acme-members' ), number_format_i18n( $total ) ) );
		?>
	</p>
	<?php if ( ! $cards ) : ?>
		<p class="acme-member-directory__empty"><?php esc_html_e( 'No members found.', 'acme-members' ); ?></p>
	<?php else : ?>
		<ul class="acme-member-directory__list">
			<?php foreach ( $cards as $acme_card ) : ?>
				<li class="acme-member-card" data-member-id="<?php echo esc_attr( (string) $acme_card['user']->ID ); ?>">
					<a class="acme-member-card__name" href="<?php echo esc_url( \Acme\Members\Members::profile_url( $acme_card['user'] ) ); ?>"><?php echo esc_html( $acme_card['user']->display_name ); ?></a>
					<?php foreach ( $acme_card['values'] as $acme_key => $acme_value ) : ?>
						<span class="acme-member-card__field acme-member-card__field--<?php echo esc_attr( $acme_key ); ?>"><?php echo esc_html( $acme_value ); ?></span>
					<?php endforeach; ?>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php endif; ?>
	<?php if ( $pages > 1 ) : ?>
		<nav class="acme-member-directory__pagination">
			<?php for ( $acme_i = 1; $acme_i <= $pages; $acme_i++ ) : ?>
				<?php if ( $acme_i === $page ) : ?>
					<span aria-current="page"><?php echo esc_html( (string) $acme_i ); ?></span>
				<?php else : ?>
					<a href="<?php echo esc_url( add_query_arg( 'members_page', $acme_i ) ); ?>"><?php echo esc_html( (string) $acme_i ); ?></a>
				<?php endif; ?>
			<?php endfor; ?>
		</nav>
	<?php endif; ?>
</div>
