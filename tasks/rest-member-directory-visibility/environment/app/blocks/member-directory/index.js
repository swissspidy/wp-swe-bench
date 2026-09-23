/**
 * Member directory block (editor). Server-rendered; no build step.
 */
( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, TextControl, RangeControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const { __ } = wp.i18n;

	registerBlockType( 'acme/member-directory', {
		edit( { attributes, setAttributes } ) {
			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Directory settings', 'acme-members' ) },
						el( TextControl, {
							label: __( 'Only members from this city', 'acme-members' ),
							value: attributes.city,
							onChange: ( city ) => setAttributes( { city } ),
						} ),
						el( RangeControl, {
							label: __( 'Members per page', 'acme-members' ),
							value: attributes.perPage,
							min: 1,
							max: 48,
							onChange: ( perPage ) => setAttributes( { perPage } ),
						} )
					)
				),
				el(
					'div',
					useBlockProps(),
					el( ServerSideRender, { block: 'acme/member-directory', attributes } )
				)
			);
		},
		save() {
			return null;
		},
	} );
} )( window.wp );
