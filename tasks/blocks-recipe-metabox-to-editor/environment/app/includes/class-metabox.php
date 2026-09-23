<?php
/**
 * "Recipe details" meta box (classic editor).
 *
 * @package Acme\Recipes
 */

namespace Acme\Recipes;

defined( 'ABSPATH' ) || exit;

/**
 * Edits ingredients, times, servings, difficulty, kitchen notes and the staff pick flag.
 */
class Metabox {

	const NONCE_ACTION = 'acme_recipes_save_details';
	const NONCE_NAME   = 'acme_recipes_details_nonce';

	/**
	 * Register hooks.
	 */
	public function register_hooks() {
		add_action( 'add_meta_boxes_' . Post_Type::POST_TYPE, array( $this, 'add' ) );
		add_action( 'save_post_' . Post_Type::POST_TYPE, array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	/**
	 * Add the meta box.
	 */
	public function add() {
		add_meta_box( 'acme-recipe-details', __( 'Recipe details', 'acme-recipes' ), array( $this, 'render' ), null, 'normal', 'high' );
	}

	/**
	 * Admin assets for the recipe edit screen.
	 *
	 * @param string $hook_suffix Admin page.
	 */
	public function enqueue( $hook_suffix ) {
		$screen = get_current_screen();
		if ( ! in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) || ! $screen || Post_Type::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$asset_file = ACME_RECIPES_DIR . 'build/admin/metabox.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}
		$asset = include $asset_file;
		wp_enqueue_script( 'acme-recipes-metabox', ACME_RECIPES_URL . 'build/admin/metabox.js', $asset['dependencies'], $asset['version'], true );
		wp_enqueue_style( 'acme-recipes-metabox', ACME_RECIPES_URL . 'build/admin/metabox.css', array(), $asset['version'] );
		wp_set_script_translations( 'acme-recipes-metabox', 'acme-recipes', ACME_RECIPES_DIR . 'languages' );
	}

	/**
	 * Meta box markup.
	 *
	 * @param \WP_Post $post Recipe.
	 */
	public function render( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );
		$ingredients = acme_recipes_get_ingredients( $post->ID );
		if ( ! $ingredients ) {
			$ingredients = array(
				array(
					'amount' => '',
					'unit'   => '',
					'item'   => '',
				),
			);
		}
		$difficulty = acme_recipes_get_difficulty( $post->ID );
		?>
		<div class="acme-recipe-details">
			<p class="acme-recipe-details__row">
				<label><?php esc_html_e( 'Prep time (minutes)', 'acme-recipes' ); ?>
					<input type="number" min="0" name="acme_recipe[prep_time]" value="<?php echo esc_attr( acme_recipes_get_minutes( $post->ID, ACME_RECIPES_META_PREP ) ); ?>" /></label>
				<label><?php esc_html_e( 'Cook time (minutes)', 'acme-recipes' ); ?>
					<input type="number" min="0" name="acme_recipe[cook_time]" value="<?php echo esc_attr( acme_recipes_get_minutes( $post->ID, ACME_RECIPES_META_COOK ) ); ?>" /></label>
				<label><?php esc_html_e( 'Servings', 'acme-recipes' ); ?>
					<input type="number" min="1" max="100" name="acme_recipe[servings]" value="<?php echo esc_attr( acme_recipes_get_servings( $post->ID ) ); ?>" /></label>
				<label><?php esc_html_e( 'Difficulty', 'acme-recipes' ); ?>
					<select name="acme_recipe[difficulty]">
						<option value=""><?php esc_html_e( '— Select —', 'acme-recipes' ); ?></option>
						<?php foreach ( acme_recipes_difficulties() as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $difficulty, $slug ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select></label>
			</p>

			<h4><?php esc_html_e( 'Ingredients', 'acme-recipes' ); ?></h4>
			<table class="acme-recipe-ingredients widefat">
				<thead><tr><th><?php esc_html_e( 'Amount', 'acme-recipes' ); ?></th><th><?php esc_html_e( 'Unit', 'acme-recipes' ); ?></th><th><?php esc_html_e( 'Ingredient', 'acme-recipes' ); ?></th><th></th></tr></thead>
				<tbody>
				<?php foreach ( $ingredients as $i => $ingredient ) : ?>
					<tr class="acme-recipe-ingredient">
						<td><input type="text" class="small-text" name="acme_recipe[ingredients][<?php echo (int) $i; ?>][amount]" value="<?php echo esc_attr( $ingredient['amount'] ); ?>" /></td>
						<td><input type="text" class="small-text" name="acme_recipe[ingredients][<?php echo (int) $i; ?>][unit]" value="<?php echo esc_attr( $ingredient['unit'] ); ?>" /></td>
						<td><input type="text" class="regular-text" name="acme_recipe[ingredients][<?php echo (int) $i; ?>][item]" value="<?php echo esc_attr( $ingredient['item'] ); ?>" /></td>
						<td><button type="button" class="button-link acme-recipe-ingredient__remove"><?php esc_html_e( 'Remove', 'acme-recipes' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button acme-recipe-ingredient__add"><?php esc_html_e( 'Add ingredient', 'acme-recipes' ); ?></button></p>

			<p>
				<label for="acme-recipe-notes"><?php esc_html_e( 'Kitchen notes (private: never shown on the site)', 'acme-recipes' ); ?></label><br />
				<textarea id="acme-recipe-notes" class="large-text" rows="3" name="acme_recipe[notes]"><?php echo esc_textarea( (string) get_post_meta( $post->ID, ACME_RECIPES_META_NOTES, true ) ); ?></textarea>
			</p>

			<?php if ( current_user_can( 'edit_others_posts' ) ) : ?>
			<p>
				<label><input type="checkbox" name="acme_recipe[staff_pick]" value="1" <?php checked( (bool) get_post_meta( $post->ID, ACME_RECIPES_META_STAFF_PICK, true ) ); ?> />
				<?php esc_html_e( 'Staff pick', 'acme-recipes' ); ?></label>
			</p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Save the meta box.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post.
	 */
	public function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE_NAME ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE_NAME ] ) ), self::NONCE_ACTION ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized field by field below.
		$data = isset( $_POST['acme_recipe'] ) && is_array( $_POST['acme_recipe'] ) ? wp_unslash( $_POST['acme_recipe'] ) : array();

		$ingredients = array();
		foreach ( (array) ( $data['ingredients'] ?? array() ) as $row ) {
			$ingredient = acme_recipes_normalize_ingredient(
				array(
					'amount' => sanitize_text_field( $row['amount'] ?? '' ),
					'unit'   => sanitize_text_field( $row['unit'] ?? '' ),
					'item'   => sanitize_text_field( $row['item'] ?? '' ),
				)
			);
			if ( $ingredient ) {
				$ingredients[] = $ingredient;
			}
		}
		update_post_meta( $post_id, ACME_RECIPES_META_INGREDIENTS, $ingredients );

		update_post_meta( $post_id, ACME_RECIPES_META_PREP, absint( $data['prep_time'] ?? 0 ) );
		update_post_meta( $post_id, ACME_RECIPES_META_COOK, absint( $data['cook_time'] ?? 0 ) );
		update_post_meta( $post_id, ACME_RECIPES_META_SERVINGS, min( 100, absint( $data['servings'] ?? 0 ) ) );

		$difficulty = sanitize_key( $data['difficulty'] ?? '' );
		if ( array_key_exists( $difficulty, acme_recipes_difficulties() ) ) {
			update_post_meta( $post_id, ACME_RECIPES_META_DIFFICULTY, $difficulty );
		} else {
			delete_post_meta( $post_id, ACME_RECIPES_META_DIFFICULTY );
		}

		update_post_meta( $post_id, ACME_RECIPES_META_NOTES, sanitize_textarea_field( $data['notes'] ?? '' ) );

		// Only editors decide what is a staff pick.
		if ( current_user_can( 'edit_others_posts' ) ) {
			if ( ! empty( $data['staff_pick'] ) ) {
				update_post_meta( $post_id, ACME_RECIPES_META_STAFF_PICK, '1' );
			} else {
				delete_post_meta( $post_id, ACME_RECIPES_META_STAFF_PICK );
			}
		}
	}
}
