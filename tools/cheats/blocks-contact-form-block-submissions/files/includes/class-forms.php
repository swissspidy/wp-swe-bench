<?php
/**
 * Contact form definitions, read from the stored block markup of a post.
 *
 * The browser never tells us which fields a form has, whether they are required or which options
 * a drop-down offers: that always comes from the saved post.
 *
 * @package Acme\Contact
 */

namespace Acme\Contact;

defined( 'ABSPATH' ) || exit;

/**
 * Form lookup.
 */
class Forms {

	const FORM_BLOCK = 'acme/contact-form';

	/**
	 * Field block name => field type.
	 */
	const FIELD_BLOCKS = array(
		'acme/field-text'     => 'text',
		'acme/field-email'    => 'email',
		'acme/field-textarea' => 'textarea',
		'acme/field-select'   => 'select',
		'acme/field-checkbox' => 'checkbox',
	);

	/**
	 * Normalize a field block into a field definition.
	 *
	 * @param array $block Parsed field block.
	 * @return array{type:string, name:string, label:string, required:bool, options:string[]}|null
	 */
	public static function field_from_block( array $block ) {
		if ( ! isset( self::FIELD_BLOCKS[ $block['blockName'] ] ) ) {
			return null;
		}
		$attrs = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$name  = isset( $attrs['name'] ) ? sanitize_key( (string) $attrs['name'] ) : '';
		if ( '' === $name ) {
			return null;
		}
		$options = array();
		if ( isset( $attrs['options'] ) && is_array( $attrs['options'] ) ) {
			foreach ( $attrs['options'] as $option ) {
				if ( is_scalar( $option ) && '' !== trim( (string) $option ) ) {
					$options[] = (string) $option;
				}
			}
		}
		$label = isset( $attrs['label'] ) && is_scalar( $attrs['label'] ) ? (string) $attrs['label'] : '';
		return array(
			'type'     => self::FIELD_BLOCKS[ $block['blockName'] ],
			'name'     => $name,
			'label'    => '' !== $label ? $label : $name,
			'required' => ! empty( $attrs['required'] ),
			'options'  => $options,
		);
	}

	/**
	 * Build a form definition from a parsed contact form block.
	 *
	 * @param array $block   Parsed block.
	 * @param int   $post_id Post.
	 * @return array{post_id:int, form_id:string, submit_label:string, success_message:string, fields:array[]}
	 */
	public static function from_block( array $block, $post_id ) {
		$attrs  = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
		$fields = array();
		foreach ( (array) $block['innerBlocks'] as $inner ) {
			$field = self::field_from_block( $inner );
			if ( $field && ! isset( $fields[ $field['name'] ] ) ) {
				$fields[ $field['name'] ] = $field;
			}
		}
		return array(
			'post_id'         => (int) $post_id,
			'form_id'         => isset( $attrs['formId'] ) ? (string) $attrs['formId'] : '',
			'submit_label'    => isset( $attrs['submitLabel'] ) && '' !== $attrs['submitLabel'] ? (string) $attrs['submitLabel'] : __( 'Send', 'acme-contact' ),
			'success_message' => isset( $attrs['successMessage'] ) ? (string) $attrs['successMessage'] : '',
			'fields'          => array_values( $fields ),
		);
	}

	/**
	 * Find a form in a post by its form ID.
	 *
	 * @param int    $post_id Post.
	 * @param string $form_id Form ID.
	 * @return array|null
	 */
	public static function find( $post_id, $form_id ) {
		$post = get_post( (int) $post_id );
		if ( ! $post || '' === (string) $form_id || ! has_block( self::FORM_BLOCK, $post ) ) {
			return null;
		}
		$found = self::search( parse_blocks( $post->post_content ), (string) $form_id );
		return $found ? self::from_block( $found, $post->ID ) : null;
	}

	/**
	 * Can visitors submit forms on this post?
	 *
	 * @param int $post_id Post.
	 * @return bool
	 */
	public static function accepts_submissions( $post_id ) {
		$post = get_post( (int) $post_id );
		return $post && 'publish' === $post->post_status && '' === $post->post_password && is_post_type_viewable( $post->post_type );
	}

	/**
	 * Recursive search.
	 *
	 * @param array[] $blocks  Parsed blocks.
	 * @param string  $form_id Form ID.
	 * @return array|null
	 */
	private static function search( array $blocks, $form_id ) {
		foreach ( $blocks as $block ) {
			if ( self::FORM_BLOCK === $block['blockName'] && isset( $block['attrs']['formId'] ) && (string) $block['attrs']['formId'] === $form_id ) {
				return $block;
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::search( $block['innerBlocks'], $form_id );
				if ( $found ) {
					return $found;
				}
			}
		}
		return null;
	}
}
