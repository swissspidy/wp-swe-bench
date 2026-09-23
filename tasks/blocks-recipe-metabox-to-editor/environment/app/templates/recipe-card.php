<?php
/**
 * Recipe card. Override in a theme: `{theme}/acme-recipes/recipe-card.php`.
 *
 * @package Acme\Recipes
 *
 * @var int    $post_id
 * @var array  $vars        { ingredients, prep, cook, servings, difficulty }
 */

defined( 'ABSPATH' ) || exit;

$acme_levels = acme_recipes_difficulties();
?>
<section class="acme-recipe-card" id="recipe-<?php echo (int) $post_id; ?>">
	<h2 class="acme-recipe-card__title"><?php esc_html_e( 'Recipe', 'acme-recipes' ); ?></h2>
	<ul class="acme-recipe-card__meta">
		<?php if ( $vars['prep'] ) : ?>
		<li class="acme-recipe-card__prep"><?php esc_html_e( 'Prep:', 'acme-recipes' ); ?> <span><?php echo esc_html( acme_recipes_format_minutes( $vars['prep'] ) ); ?></span></li>
		<?php endif; ?>
		<?php if ( $vars['cook'] ) : ?>
		<li class="acme-recipe-card__cook"><?php esc_html_e( 'Cook:', 'acme-recipes' ); ?> <span><?php echo esc_html( acme_recipes_format_minutes( $vars['cook'] ) ); ?></span></li>
		<?php endif; ?>
		<?php if ( $vars['servings'] ) : ?>
		<li class="acme-recipe-card__servings"><?php esc_html_e( 'Serves:', 'acme-recipes' ); ?> <span><?php echo (int) $vars['servings']; ?></span></li>
		<?php endif; ?>
		<?php if ( $vars['difficulty'] ) : ?>
		<li class="acme-recipe-card__difficulty"><?php esc_html_e( 'Difficulty:', 'acme-recipes' ); ?> <span><?php echo esc_html( $acme_levels[ $vars['difficulty'] ] ); ?></span></li>
		<?php endif; ?>
	</ul>
	<?php if ( $vars['ingredients'] ) : ?>
	<h3 class="acme-recipe-card__subtitle"><?php esc_html_e( 'Ingredients', 'acme-recipes' ); ?></h3>
	<ul class="acme-recipe-card__ingredients">
		<?php foreach ( $vars['ingredients'] as $acme_ingredient ) : ?>
		<li class="acme-recipe-card__ingredient"><span class="acme-recipe-card__amount"><?php echo esc_html( trim( $acme_ingredient['amount'] . ' ' . $acme_ingredient['unit'] ) ); ?></span> <span class="acme-recipe-card__item"><?php echo esc_html( $acme_ingredient['item'] ); ?></span></li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>
</section>
