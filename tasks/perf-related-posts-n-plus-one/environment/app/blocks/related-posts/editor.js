/**
 * Editor side of the "Related posts" block. Plain script (no build step): the
 * block is rendered on the server, the editor shows a server-side preview.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var __ = wp.i18n.__;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var RangeControl = wp.components.RangeControl;
	var ServerSideRender = wp.serverSideRender;

	wp.blocks.registerBlockType( 'acme/related-posts', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var postId = attributes.postId || ( props.context && props.context.postId ) || 0;

			return el(
				'div',
				useBlockProps(),
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Settings', 'acme-related' ) },
						el( TextControl, {
							label: __( 'Heading (leave empty for the default)', 'acme-related' ),
							value: attributes.heading,
							onChange: function ( value ) {
								setAttributes( { heading: value } );
							},
						} ),
						el( RangeControl, {
							label: __( 'Number of posts (0 = default)', 'acme-related' ),
							min: 0,
							max: 12,
							value: attributes.count,
							onChange: function ( value ) {
								setAttributes( { count: value || 0 } );
							},
						} ),
						el( TextControl, {
							label: __( 'Show related posts of post ID (0 = this post)', 'acme-related' ),
							type: 'number',
							value: attributes.postId,
							onChange: function ( value ) {
								setAttributes( { postId: parseInt( value, 10 ) || 0 } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'acme/related-posts',
					attributes: Object.assign( {}, attributes, { postId: postId } ),
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
