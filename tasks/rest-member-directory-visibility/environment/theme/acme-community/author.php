<?php
/**
 * Author archive: member card + the author's posts.
 *
 * @package Acme\Community
 */

get_header();
$acme_author = get_queried_object();
?>
<main id="primary" class="author-archive">
	<h1 class="page-title"><?php echo esc_html( $acme_author->display_name ); ?></h1>
	<?php if ( get_the_author_meta( 'description', $acme_author->ID ) ) : ?>
		<div class="author-bio"><?php echo wp_kses_post( wpautop( get_the_author_meta( 'description', $acme_author->ID ) ) ); ?></div>
	<?php endif; ?>
	<?php
	if ( function_exists( 'acme_members_author_card' ) ) {
		acme_members_author_card( $acme_author->ID );
	}
	?>
	<?php
	while ( have_posts() ) :
		the_post();
		?>
		<article <?php post_class(); ?>>
			<h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
		</article>
	<?php endwhile; ?>
</main>
<?php
get_footer();
