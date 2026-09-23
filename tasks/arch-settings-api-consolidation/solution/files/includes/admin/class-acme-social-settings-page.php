<?php
/**
 * Base class of the Acme Social settings screens.
 *
 * @package Acme_Social
 */

defined( 'ABSPATH' ) || exit;

/**
 * A screen that edits one section of `acme_social_settings` through the
 * standard options.php save flow.
 */
abstract class Acme_Social_Settings_Page {

	/**
	 * Menu slug (also the option group).
	 *
	 * @var string
	 */
	protected $slug = '';

	/**
	 * Section key (see Acme_Social_Settings::fields()).
	 *
	 * @var string
	 */
	protected $section = '';

	/**
	 * Screen title.
	 *
	 * @return string
	 */
	abstract public function title();

	/**
	 * Intro text below the title.
	 *
	 * @return string
	 */
	public function description() {
		return '';
	}

	/**
	 * Hooks.
	 */
	public function register() {
		add_action( 'admin_init', array( $this, 'register_fields' ) );
	}

	/**
	 * Registers the section and its fields.
	 */
	public function register_fields() {
		add_settings_section(
			'acme-social-' . $this->section,
			'',
			function () {
				if ( $this->description() ) {
					echo '<p>' . esc_html( $this->description() ) . '</p>';
				}
			},
			$this->slug
		);
		foreach ( Acme_Social_Settings::fields() as $key => $field ) {
			if ( $field['section'] !== $this->section ) {
				continue;
			}
			$label_for = in_array( $field['type'], array( 'bool', 'networks', 'post_types' ), true ) ? array() : array( 'label_for' => 'acme-social-' . $key );
			add_settings_field(
				'acme-social-' . $key,
				$field['label'],
				array( $this, 'render_field' ),
				$this->slug,
				'acme-social-' . $this->section,
				array_merge(
					$label_for,
					array(
						'key'  => $key,
						'type' => $field['type'],
					)
				)
			);
		}
	}

	/**
	 * Name attribute of a field.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	protected function name( $key ) {
		return Acme_Social_Settings::OPTION . '[' . $key . ']';
	}

	/**
	 * Renders one field.
	 *
	 * @param array $args Field args.
	 */
	public function render_field( $args ) {
		$key   = $args['key'];
		$value = Acme_Social_Settings::get( $key );
		$id    = 'acme-social-' . $key;

		switch ( $args['type'] ) {
			case 'bool':
				$texts = array(
					'share_enabled' => __( 'Show share buttons on the selected post types', 'acme-social' ),
					'og_enabled'    => __( 'Output Open Graph and Twitter card tags', 'acme-social' ),
				);
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s /> %4$s</label>',
					esc_attr( $id ),
					esc_attr( $this->name( $key ) ),
					checked( (bool) $value, true, false ),
					esc_html( $texts[ $key ] ?? '' )
				);
				break;

			case 'networks':
			case 'post_types':
				$choices = array();
				if ( 'networks' === $args['type'] ) {
					foreach ( acme_social_available_networks() as $slug => $network ) {
						$choices[ $slug ] = $network['label'];
					}
				} else {
					foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $type ) {
						if ( 'attachment' !== $type->name ) {
							$choices[ $type->name ] = $type->labels->name;
						}
					}
				}
				echo '<fieldset>';
				foreach ( $choices as $slug => $label ) {
					printf(
						'<label><input type="checkbox" name="%1$s[]" value="%2$s" %3$s /> %4$s</label><br />',
						esc_attr( $this->name( $key ) ),
						esc_attr( $slug ),
						checked( in_array( $slug, (array) $value, true ), true, false ),
						esc_html( $label )
					);
				}
				echo '</fieldset>';
				break;

			case 'position':
			case 'button_style':
			case 'twitter_card':
				$choices = array(
					'position'     => acme_social_positions(),
					'button_style' => acme_social_button_styles(),
					'twitter_card' => acme_social_twitter_card_types(),
				)[ $args['type'] ];
				printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $this->name( $key ) ) );
				foreach ( $choices as $choice => $label ) {
					printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $choice ), selected( $value, $choice, false ), esc_html( $label ) );
				}
				echo '</select>';
				break;

			case 'image':
				printf(
					'<input type="number" min="0" class="small-text" id="%1$s" name="%2$s" value="%3$s" /><p class="description">%4$s</p>',
					esc_attr( $id ),
					esc_attr( $this->name( $key ) ),
					esc_attr( (string) $value ),
					esc_html__( 'Used when a post has no featured image.', 'acme-social' )
				);
				break;

			default:
				$placeholders = array(
					'twitter'   => '@acme',
					'facebook'  => 'https://www.facebook.com/…',
					'instagram' => 'https://www.instagram.com/…',
					'linkedin'  => 'https://www.linkedin.com/company/…',
					'youtube'   => 'https://www.youtube.com/@…',
				);
				printf(
					'<input type="text" class="regular-text" id="%1$s" name="%2$s" value="%3$s" placeholder="%4$s" />',
					esc_attr( $id ),
					esc_attr( $this->name( $key ) ),
					esc_attr( 'twitter' === $key && '' !== $value ? '@' . $value : (string) $value ),
					esc_attr( $placeholders[ $key ] ?? '' )
				);
		}
	}

	/**
	 * Renders the screen.
	 */
	public function render() {
		if ( ! current_user_can( Acme_Social_Settings::capability() ) ) {
			return;
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( $this->title() ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
				<?php
				settings_fields( $this->slug );
				do_settings_sections( $this->slug );
				submit_button( __( 'Save Changes', 'acme-social' ) );
				?>
			</form>
		</div>
		<?php
	}
}
