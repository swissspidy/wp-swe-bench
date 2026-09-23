<?php
/**
 * Template tags for themes.
 *
 * @package Acme\Members
 */

defined( 'ABSPATH' ) || exit;

/**
 * Print the member card of an author (used by the theme on author archives).
 *
 * Markup:
 * <aside class="acme-author-card" data-member-id="…">
 *   <p class="acme-author-card__name">…</p>
 *   <p class="acme-author-card__field acme-author-card__field--{key}">…</p> …
 *   <a class="acme-author-card__profile" href="…">View profile</a>
 * </aside>
 *
 * @param int $user_id Author.
 */
function acme_members_author_card( $user_id ) {
	$user_id = (int) $user_id;
	$viewer  = get_current_user_id();
	if ( ! Acme\Members\Members::is_member( $user_id ) || ! Acme\Members\Visibility::can_view_profile( $user_id, $viewer ) ) {
		return;
	}
	$user   = get_userdata( $user_id );
	$values = Acme\Members\Visibility::visible_fields( $user_id, $viewer );
	unset( $values['bio'] ); // The theme shows the WordPress biography already.
	?>
	<aside class="acme-author-card" data-member-id="<?php echo esc_attr( (string) $user_id ); ?>">
		<p class="acme-author-card__name"><?php echo esc_html( $user->display_name ); ?></p>
		<?php foreach ( $values as $key => $value ) : ?>
			<p class="acme-author-card__field acme-author-card__field--<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $value ); ?></p>
		<?php endforeach; ?>
		<a class="acme-author-card__profile" href="<?php echo esc_url( Acme\Members\Members::profile_url( $user ) ); ?>"><?php esc_html_e( 'View profile', 'acme-members' ); ?></a>
	</aside>
	<?php
}

/**
 * Profile URL of a member.
 *
 * @param int $user_id Member.
 * @return string
 */
function acme_members_profile_url( $user_id ) {
	return Acme\Members\Members::profile_url( $user_id );
}
